<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Follow-up of deliveries the client or a supplier makes for a project: Enersys batteries (605, ordered
 * by the client) and electrical boards (610–650) per board supplier. A supplier's layout decides the
 * list: "simple" (PO, delivery date, delivered) or "configured" (also actual delivery, configuration
 * date and completed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            // simple or configured.
            $table->string('layout', 20)->default('simple');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('delivery_follow_ups', function (Blueprint $table) {
            $table->id();
            // enersys or board.
            $table->string('kind', 20)->index();
            $table->foreignId('board_supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('po_number', 30)->nullable();
            // Planned or expected delivery date.
            $table->date('delivery_date')->nullable();
            $table->boolean('delivered')->default(false);
            // Actual delivery: stamped when "delivered" is ticked, or filled in (configured layout).
            $table->date('delivered_on')->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('configured_on')->nullable();
            // Installed and configured (configured layout).
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        DB::table('board_suppliers')->insert(array_map(fn (array $supplier): array => [...$supplier, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now], [
            ['name' => 'ELP', 'layout' => 'simple', 'sort' => 1],
            ['name' => 'P&V panels', 'layout' => 'simple', 'sort' => 2],
            ['name' => 'CE+T', 'layout' => 'configured', 'sort' => 3],
            ['name' => 'Automation LP (Halle)', 'layout' => 'configured', 'sort' => 4],
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_follow_ups');
        Schema::dropIfExists('board_suppliers');
    }
};
