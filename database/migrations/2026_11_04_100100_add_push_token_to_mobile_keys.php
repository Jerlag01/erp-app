<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Expo push token of the phone that holds the key, for push notifications.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_keys', function (Blueprint $table): void {
            $table->string('push_token', 255)->nullable()->after('device_id');
            $table->timestamp('push_token_updated_at')->nullable()->after('push_token');
        });
    }

    public function down(): void
    {
        Schema::table('mobile_keys', function (Blueprint $table): void {
            $table->dropColumn(['push_token', 'push_token_updated_at']);
        });
    }
};
