<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Messages sent from the platform to the mobile app, written in each language: every recipient
     * gets the text of their own language, and the app keeps them in "Messages".
     */
    public function up(): void
    {
        Schema::create('push_messages', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->json('body');
            $table->string('audience', 20);
            $table->json('team_ids')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('push_message_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('push_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['push_message_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_message_user');
        Schema::dropIfExists('push_messages');
    }
};
