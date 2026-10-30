<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IR days (inhaalrust) per interim: a company IR day taken away (the interim works that day) or an IR
 * day of their own on another date. Without a row the company IR days apply as for everyone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interim_recuperation_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            // true: an IR day off for this interim; false: the company IR day of that date does not apply.
            $table->boolean('is_day_off');
            $table->string('name', 100)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interim_recuperation_days');
    }
};
