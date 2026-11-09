<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personal keys in the links of a GPW request mail: each recipient approves or refuses directly from
 * the mail, without logging in. A key only works through its own link, once, until the end of the
 * requested period; a new mailing replaces the unused keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gpw_request_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gpw_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_site_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('email', 150);
            $table->string('locale', 5);
            // SHA-256 of the key in the link: the key itself is never stored.
            $table->char('token_hash', 64)->unique();
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gpw_request_keys');
    }
};
