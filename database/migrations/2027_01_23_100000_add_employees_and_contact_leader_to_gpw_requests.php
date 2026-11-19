<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A GPW request names the employees who will do the work and the project leader who is the contact
 * person in the mail. The stored GPW mail templates still showing the old "Requested by" line get the
 * new lines (a template that was rewritten by hand is left alone; it can use the new placeholders).
 */
return new class extends Migration
{
    /** @var array<string, array{0: string, 1: string}> */
    private const LINES = [
        'nl' => ['Aangevraagd door {requested_by} ({requester_email}).', "Medewerkers: {employees}\n\nContactpersoon: {contact_person}."],
        'fr' => ['Demandé par {requested_by} ({requester_email}).', "Collaborateurs : {employees}\n\nPersonne de contact : {contact_person}."],
        'en' => ['Requested by {requested_by} ({requester_email}).', "Employees: {employees}\n\nContact person: {contact_person}."],
    ];

    public function up(): void
    {
        Schema::table('gpw_requests', function (Blueprint $table): void {
            $table->json('employee_ids')->nullable()->after('contact_ids');
            $table->foreignId('contact_user_id')->nullable()->after('requested_by')->constrained('users')->nullOnDelete();
        });

        foreach (self::LINES as $locale => [$old, $new]) {
            DB::table('mail_templates')->where('key', 'gpw_request')->where('locale', $locale)->where('body', 'like', '%'.$old.'%')
                ->get(['id', 'body'])
                ->each(fn (object $template) => DB::table('mail_templates')->where('id', $template->id)->update(['body' => str_replace($old, $new, $template->body)]));
        }
    }

    public function down(): void
    {
        foreach (self::LINES as $locale => [$old, $new]) {
            DB::table('mail_templates')->where('key', 'gpw_request')->where('locale', $locale)->where('body', 'like', '%'.$new.'%')
                ->get(['id', 'body'])
                ->each(fn (object $template) => DB::table('mail_templates')->where('id', $template->id)->update(['body' => str_replace($new, $old, $template->body)]));
        }

        Schema::table('gpw_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('contact_user_id');
            $table->dropColumn('employee_ids');
        });
    }
};
