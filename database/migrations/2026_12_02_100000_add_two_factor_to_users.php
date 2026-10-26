<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two-step verification for ERP and client portal users: an authenticator app (secret and recovery
 * codes, stored encrypted) and/or a code by e-mail. Each user switches it on in their profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'client_users'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->text('app_authentication_secret')->nullable();
                $table->text('app_authentication_recovery_codes')->nullable();
                $table->boolean('has_email_authentication')->default(false);
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'client_users'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes', 'has_email_authentication']);
            });
        }
    }
};
