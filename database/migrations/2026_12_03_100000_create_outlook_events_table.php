<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Outlook events of absences, holidays (also IR days) and trainings: one per employee whose
     * calendar has it (planning items keep theirs on the item itself).
     */
    public function up(): void
    {
        Schema::create('outlook_events', function (Blueprint $table) {
            $table->id();
            $table->morphs('source');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('mailbox', 255);
            $table->string('event_id', 255);
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlook_events');
    }
};
