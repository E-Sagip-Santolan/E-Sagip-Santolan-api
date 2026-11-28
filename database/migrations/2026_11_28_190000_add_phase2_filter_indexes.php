<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes for columns used in Phase 2 filters and logs.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('role');
            $table->index('status');
            $table->index('is_active');
        });

        Schema::table('volunteer_profiles', function (Blueprint $table) {
            $table->index('availability');
            $table->index('address_area');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index('module');
            $table->index('action');
            $table->index('created_at');
        });
    }

    /**
     * Remove the filter indexes.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['status']);
            $table->dropIndex(['is_active']);
        });

        Schema::table('volunteer_profiles', function (Blueprint $table) {
            $table->dropIndex(['availability']);
            $table->dropIndex(['address_area']);
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['module']);
            $table->dropIndex(['action']);
            $table->dropIndex(['created_at']);
        });
    }
};
