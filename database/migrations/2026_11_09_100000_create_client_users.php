<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Users of the client portal ("Global overview for Client", e.g. Proximus managers): their own accounts,
 * apart from the ERP's users, with per user what they may see. Their password-reset links get their own
 * table, so an e-mail address used by both an ERP user and a client user never mixes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('company')->nullable();
            $table->string('password');
            $table->string('locale', 5)->default('nl');
            $table->boolean('is_active')->default(true);
            $table->json('permissions')->nullable();
            $table->json('project_categories')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('client_password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_password_reset_tokens');
        Schema::dropIfExists('client_users');
    }
};
