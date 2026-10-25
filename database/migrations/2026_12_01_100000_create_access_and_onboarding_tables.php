<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Access control per employee (a badge of 7 digits and/or an electronic key of 4 digits with CLAD
 * clearance; a security tag of 2 digits with its access), and onboarding: documents per language that
 * new users open and sign before using the platform. Users that exist now count as onboarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained()->cascadeOnDelete();
            $table->char('badge_number', 7)->nullable()->unique();
            $table->char('key_number', 4)->nullable()->unique();
            $table->boolean('has_clad')->default(false);
            $table->text('clearance')->nullable();
            $table->timestamps();
        });

        Schema::create('security_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained()->cascadeOnDelete();
            $table->char('tag_number', 2)->unique();
            // The name as set in the security system.
            $table->string('system_name', 100)->nullable();
            $table->boolean('access_warehouse')->default(false);
            $table->boolean('access_office')->default(false);
            $table->boolean('door_keys')->default(false);
            $table->timestamps();
        });

        Schema::create('onboarding_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('path');
            $table->string('original_name');
            $table->string('locale', 5)->index();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('onboarding_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('onboarding_document_id')->constrained()->cascadeOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->string('signer_name', 150)->nullable();
            $table->string('signature_path')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'onboarding_document_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('onboarding_completed_at')->nullable();
        });

        // Everyone in the system now has done their onboarding.
        DB::table('users')->update(['onboarding_completed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('onboarding_completed_at');
        });

        Schema::dropIfExists('onboarding_signatures');
        Schema::dropIfExists('onboarding_documents');
        Schema::dropIfExists('security_tags');
        Schema::dropIfExists('access_badges');
    }
};
