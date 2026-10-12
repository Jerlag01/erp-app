<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A medical check-up can hold several files (evaluation form, attestations…) instead of one PDF.
 * documents is the list of stored paths, document_names maps each path to its original file name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_medical_checks', function (Blueprint $table) {
            $table->json('documents')->nullable()->after('remarks');
            $table->json('document_names')->nullable()->after('documents');
        });

        DB::table('employee_medical_checks')->whereNotNull('document')->orderBy('id')->each(function (object $check): void {
            DB::table('employee_medical_checks')->where('id', $check->id)->update([
                'documents' => json_encode([$check->document]),
                'document_names' => json_encode([$check->document => $check->document_name ?: basename($check->document)]),
            ]);
        });

        Schema::table('employee_medical_checks', function (Blueprint $table) {
            $table->dropColumn(['document', 'document_name']);
        });
    }

    public function down(): void
    {
        Schema::table('employee_medical_checks', function (Blueprint $table) {
            $table->string('document')->nullable()->after('remarks');
            $table->string('document_name')->nullable()->after('document');
        });

        DB::table('employee_medical_checks')->whereNotNull('documents')->orderBy('id')->each(function (object $check): void {
            $path = json_decode($check->documents, true)[0] ?? null;

            DB::table('employee_medical_checks')->where('id', $check->id)->update([
                'document' => $path,
                'document_name' => $path ? (json_decode((string) $check->document_names, true)[$path] ?? null) : null,
            ]);
        });

        Schema::table('employee_medical_checks', function (Blueprint $table) {
            $table->dropColumn(['documents', 'document_names']);
        });
    }
};
