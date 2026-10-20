<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_users', function (Blueprint $table) {
            $table->timestamp('welcome_sent_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('client_users', function (Blueprint $table) {
            $table->dropColumn('welcome_sent_at');
        });
    }
};
