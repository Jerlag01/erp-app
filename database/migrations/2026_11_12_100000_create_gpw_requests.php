<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GPW requests: an employee asks the COW site's contact person for a period to carry out a project;
 * the client approves (or refuses) through the mail link or the client portal, after which the
 * requester plans a team in that period. Contact persons get a language for these mails.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_site_contacts', function (Blueprint $table) {
            $table->string('locale', 5)->default('nl')->after('phone');
        });

        Schema::create('gpw_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            // Who it was sent to, kept as it was at the time.
            $table->foreignId('work_site_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contact_name', 150);
            $table->string('contact_email', 150);
            $table->string('locale', 5)->default('nl');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->text('remarks')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('mailed_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by_client_user_id')->nullable()->constrained('client_users')->nullOnDelete();
            $table->string('decided_by_name', 150)->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('planned_at')->nullable();
            $table->foreignId('planned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gpw_requests');

        Schema::table('work_site_contacts', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
