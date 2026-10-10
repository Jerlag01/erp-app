<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prestations (timesheets, the Excel "Prestatiefiche"): per employee per working day six rows of
 * odometer readings and times (start at the warehouse, the COW site, three extra sites, end at the
 * warehouse) with imputation (project) and description (COW code), a lunch break toggle and remarks.
 * Per week the carried-over kilometres and overtime. Trainings are planned in the planning module and
 * count, like vacation, as a worked day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('company')->nullable();
            $table->string('street', 150)->nullable();
            $table->string('house_number', 20)->nullable();
            $table->string('addition', 20)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->string('contact_email')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['start_date', 'end_date']);
        });

        Schema::create('training_user', function (Blueprint $table) {
            $table->foreignId('training_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['training_id', 'user_id']);
        });

        Schema::create('timesheet_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->boolean('lunch_break')->default(true);
            // Six rows: [{kind, km_departure, km_arrival, arrival, departure, project_id, imputation, description}].
            $table->json('rows')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });

        Schema::create('timesheet_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The Monday of the week.
            $table->date('week_start');
            $table->string('plate_number', 20)->nullable();
            // "Report": carried over from the previous week unless an administrator sets it.
            $table->integer('km_carried')->nullable();
            $table->decimal('overtime_carried', 7, 2)->nullable();
            $table->decimal('expected_hours', 5, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_weeks');
        Schema::dropIfExists('timesheet_days');
        Schema::dropIfExists('training_user');
        Schema::dropIfExists('trainings');
    }
};
