<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Overzicht Levering Batterijen": the battery orders per 604 project with the order confirmation of
 * the supplier, battery type, pieces, planned and actual delivery date, executed and total cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('battery_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('order_confirmation', 30)->nullable();
            $table->foreignId('battery_model_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('pieces')->nullable();
            $table->date('planned_delivery_date')->nullable();
            // Ticked when the batteries arrive; the date and time come from the system.
            $table->boolean('delivered')->default(false);
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('executed')->default(false);
            $table->decimal('total_cost', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['delivered', 'executed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battery_deliveries');
    }
};
