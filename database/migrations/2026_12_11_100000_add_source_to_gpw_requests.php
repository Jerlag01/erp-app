<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * GPW requests can also be added by hand (asked through a personal mail): "manual", without a mail
     * from the platform and possibly without an e-mail address.
     */
    public function up(): void
    {
        Schema::table('gpw_requests', function (Blueprint $table) {
            $table->string('source', 10)->default('mail')->after('status');
            $table->string('contact_email', 150)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('gpw_requests', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
