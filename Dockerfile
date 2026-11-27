FROM php:8.3-apache

# System libraries and PHP extensions (PostgreSQL for Neon)
RUN apt-get update && apt-get install -y libpq-dev libzip-dev unzip git \
    && docker-php-ext-install pdo_pgsql pgsql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Serve Laravel from /public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

# Laravel needs to write here
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Render gives a PORT variable; make Apache listen on it. No migrations on start.
CMD sed -i "s/Listen 80/Listen ${PORT:-80}/" /etc/apache2/ports.conf \
    && sed -i "s/:80>/:${PORT:-80}>/" /etc/apache2/sites-available/000-default.conf \
    && apache2-foreground