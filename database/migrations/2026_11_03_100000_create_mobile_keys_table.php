<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The mobile app signs in once with e-mail and password and then gets a key and secret for that
 * device, valid for a year. Only a hash of the secret is stored. Keys are revoked when the login is
 * switched off (e.g. the employee left), on logout, or by an administrator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40)->unique();
            $table->char('secret_hash', 64);
            $table->string('device_name')->nullable();
            $table->string('device_id', 100)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 20)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_keys');
    }
};
