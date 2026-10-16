<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cable stripping ("Kabel ontmantelen"), like the Excel: per line the date, the imputation (COW site),
 * who worked, from–until and the weighings before and after stripping. Totals are calculated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cable_stripping_lines', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            // "Imputatie - Werf": a COW code or free text such as "Niet gelabeld".
            $table->string('imputation', 100)->nullable()->index();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            // Kilograms, as weighed.
            $table->decimal('weight_before', 10, 2)->nullable()->comment('Totaal gewicht met bak en kabel VOOR ontmanteling');
            $table->decimal('empty_bin_weight', 10, 2)->nullable()->comment('Gewicht lege bak voor ontmantelde kabel');
            $table->decimal('weight_after', 10, 2)->nullable()->comment('Totaal gewicht van kabel met bak NA ontmanteling');
            $table->string('bin_number', 30)->nullable()->comment('PI bak nummer');
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cable_stripping_line_user', function (Blueprint $table) {
            $table->foreignId('cable_stripping_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['cable_stripping_line_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cable_stripping_line_user');
        Schema::dropIfExists('cable_stripping_lines');
    }
};
