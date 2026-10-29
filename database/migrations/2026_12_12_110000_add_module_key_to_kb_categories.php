<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The module a (manual) category is about: its articles are only shown to those with access to it.
     */
    public function up(): void
    {
        Schema::table('kb_categories', function (Blueprint $table) {
            $table->string('module_key', 50)->nullable()->after('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('kb_categories', function (Blueprint $table) {
            $table->dropColumn('module_key');
        });
    }
};
