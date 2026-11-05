<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planning notifications (phone pushes and e-mails) held back while the employee is on leave (or a public
 * holiday / IR day): sent on the last day of that leave (see LeavePushDelay and planning:send-held).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('held_planning_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 10);
            $table->json('payload');
            $table->dateTime('send_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('held_planning_notifications');
    }
};
