<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a client portal user sees by default: the projects assigned to them, and those at the COW sites
 * of the contact person they are linked to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_users', function (Blueprint $table) {
            $table->foreignId('work_site_contact_id')->nullable()->after('company')->constrained()->nullOnDelete();
        });

        Schema::create('client_user_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['client_user_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_user_project');

        Schema::table('client_users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_site_contact_id');
        });
    }
};
