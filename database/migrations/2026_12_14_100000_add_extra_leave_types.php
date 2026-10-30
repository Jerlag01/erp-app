<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * More absences: the reason of a klein verlet (KV), the accident report of a work accident (WO) and
     * the parental leave an OV day belongs to; the notice period of an employee (sollicitatieverlof, SV);
     * and the parental leaves administration enables per employee (form, child, period, certificate).
     */
    public function up(): void
    {
        Schema::create('parental_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('child_name', 150);
            $table->date('child_birth_date');
            $table->string('form', 10);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('document')->nullable();
            $table->string('document_name')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('absences', function (Blueprint $table) {
            $table->string('reason', 40)->nullable()->after('days');
            $table->foreignId('incident_id')->nullable()->after('reason')->constrained()->nullOnDelete();
            $table->foreignId('parental_leave_id')->nullable()->after('incident_id')->constrained()->nullOnDelete();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->date('notice_started_on')->nullable()->after('employment_date');
            $table->date('notice_ends_on')->nullable()->after('notice_started_on');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['notice_started_on', 'notice_ends_on']);
        });

        Schema::table('absences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parental_leave_id');
            $table->dropConstrainedForeignId('incident_id');
            $table->dropColumn('reason');
        });

        Schema::dropIfExists('parental_leaves');
    }
};
