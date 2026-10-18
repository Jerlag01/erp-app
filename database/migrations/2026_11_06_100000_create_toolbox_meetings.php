<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Toolbox meetings: a safety instruction with text (images anywhere in it) and attachments, which every
 * employee reads and then signs. A participant row keeps what the employee read and downloaded, and
 * their signature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('toolbox_meetings', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('subject_nl')->nullable();
            $table->string('subject_fr')->nullable();
            $table->string('location')->nullable();
            $table->date('meeting_month');
            $table->json('instructors')->nullable();
            $table->longText('body')->nullable();
            $table->json('attachments')->nullable();
            $table->json('attachment_names')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('toolbox_meeting_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('toolbox_meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('text_read_at')->nullable();
            $table->json('downloaded')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signer_name', 150)->nullable();
            $table->string('signature_path')->nullable();
            $table->timestamps();
            $table->unique(['toolbox_meeting_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('toolbox_meeting_participants');
        Schema::dropIfExists('toolbox_meetings');
    }
};
