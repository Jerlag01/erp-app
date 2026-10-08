<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Absences entered by administrators (vacation module): medical leave, overtime taken as paid
 * leave and family leave (unpaid leave for compelling family reasons), each with an optional
 * certificate that the administrator or, when missing, the employee uploads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->index();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('half_day')->default(false);
            $table->decimal('days', 5, 1)->default(0);
            $table->text('note')->nullable();
            $table->string('document')->nullable();
            $table->string('document_name')->nullable();
            // "admin" (locked for the employee) or "employee".
            $table->string('document_source', 10)->nullable();
            $table->timestamp('document_uploaded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'start_date']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absences');
    }
};
