<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * As-built files: the register of standard files per category (evacuation routes and external
 * influences per COW-code, technical documents as a register to pick from), the as-built of a
 * project with its status and the BTV inspection, and its files (linked from the register or
 * uploaded to that as-built by employees or the client).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asbuilt_library_files', function (Blueprint $table) {
            $table->id();
            $table->string('category', 40)->index();
            $table->foreignId('work_site_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title', 255);
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('uploaded_by_client_user_id')->nullable()->constrained('client_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('asbuilts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('in_progress')->index();
            $table->date('btv_date')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('zip_path', 500)->nullable();
            $table->timestamp('client_notified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('asbuilt_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asbuilt_id')->constrained()->cascadeOnDelete();
            // A category of the as-built, or "general" for what the client hands in at once (often a ZIP).
            $table->string('category', 40)->index();
            $table->foreignId('asbuilt_library_file_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('path', 500)->nullable();
            $table->string('original_name', 255)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('uploaded_by_client_user_id')->nullable()->constrained('client_users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['asbuilt_id', 'asbuilt_library_file_id'], 'asbuilt_library_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asbuilt_files');
        Schema::dropIfExists('asbuilts');
        Schema::dropIfExists('asbuilt_library_files');
    }
};
