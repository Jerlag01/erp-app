<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dashboard blocks people build themselves: a counter, a follow-up list or a status overview on any
 * list they can open, with their own filters, shown as numbers or percentages. Administrators can
 * share a block with everyone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('panel', 20)->default('app');
            $table->string('type', 20);
            $table->string('title');
            $table->string('source', 100);
            $table->json('config')->nullable();
            $table->string('column_span', 10)->default('1');
            $table->boolean('is_shared')->default(false);
            $table->timestamps();
            $table->index(['panel', 'is_shared']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_widgets');
    }
};
