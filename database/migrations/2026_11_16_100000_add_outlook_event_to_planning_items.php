<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The event a planning item made in the employee's Outlook calendar (Microsoft 365), and in which
 * mailbox, so changing or removing the item changes or removes that event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planning_items', function (Blueprint $table) {
            $table->string('outlook_event_id', 255)->nullable()->after('updated_by');
            $table->string('outlook_mailbox', 255)->nullable()->after('outlook_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('planning_items', function (Blueprint $table) {
            $table->dropColumn(['outlook_event_id', 'outlook_mailbox']);
        });
    }
};
