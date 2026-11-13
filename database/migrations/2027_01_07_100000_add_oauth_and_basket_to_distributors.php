<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DigiKey: the refresh token of the user login (3-legged OAuth, encrypted) and until when it is valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distributors', function (Blueprint $table): void {
            $table->text('api_refresh_token')->nullable()->after('api_customer_number');
            $table->timestamp('api_refresh_expires_at')->nullable()->after('api_refresh_token');
        });
    }

    public function down(): void
    {
        Schema::table('distributors', function (Blueprint $table): void {
            $table->dropColumn(['api_refresh_token', 'api_refresh_expires_at']);
        });
    }
};
