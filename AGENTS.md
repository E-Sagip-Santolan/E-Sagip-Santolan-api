# E-Sagip API: project rules

Read @docs/context.md and @docs/api.md before starting any task.

## What this repo is
Laravel REST API for E-Sagip, a volunteer management system for Barangay Santolan.
Clients: a Flutter Android app and a Next.js admin portal. Database: PostgreSQL
(local) and Neon (live). Deployed on Render with Docker.

## Source of truth
- docs/api.md defines every endpoint and JSON shape. Follow it exactly.
- If a shape must change, update docs/api.md in the same change and tell me.
- Success: { data, message }. Errors: { message, errors }. Never return a stack trace.
- Table design comes from docs/erd.dbml when it exists. Do not invent tables or columns.

## Security (this repo is PUBLIC)
- Never read, print, copy or commit values from .env, or any key, token or Neon string.
- Only use placeholders in examples. Use dummy data only.
- Check permissions on the server (middleware or policies), never only in the frontend.
- Hash passwords. Never log passwords or tokens.

## How to work with me
- Plan first: list the files you will change and the permission checks, then wait for my approval.
- One endpoint per task. Small steps.
- Beginner-friendly code with short comments. Explain every command before I run it.
- Do not add packages without asking.
- After each change, give me curl commands to test success, 401, 403 and 422.
- Do not run git commit, git push or migrations unless I ask. I do those myself.

## Code rules
- PHP 8.3 compatible code only (composer platform is 8.3.0).
- Validate with Form Request classes. Return models through API Resource classes.
- Write a Feature test for every endpoint.
- Never edit a migration that is already merged. Add a new one.
- Do not run migrations on container start.

## Git
- Work on a feature branch, never on main. main is protected and needs a pull request.

## Out of scope for Phase 2
Operations, attendance, community, reports, dashboard, settings and the chatbot.
<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>
