<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One GPW request (one mail, one approval) can cover several COW codes of a project — the COW codes of an
 * intervention, the origin and destination of a battery move — each with its own period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gpw_request_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gpw_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cow_code', 50)->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gpw_request_sites');
    }
};
