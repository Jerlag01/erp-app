<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BAE orders (the Excel "calc.xlsx": sheet Input to sheet Order), one per 604 project. The battery
 * models get the BAE price per cell and their racks the BAE rack price and the connectors per battery
 * (Excel sheet "Batterijen"), so the order lines fill themselves in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battery_models', function (Blueprint $table) {
            $table->decimal('bae_cell_price', 10, 2)->nullable()->after('height_mm');
        });

        Schema::table('battery_racks', function (Blueprint $table) {
            // Purchase price of the rack per battery at BAE.
            $table->decimal('bae_price', 10, 2)->nullable()->after('price');
            $table->unsignedSmallInteger('intercell_count')->default(0);
            $table->string('intercell_code', 40)->nullable();
            $table->unsignedSmallInteger('step_count')->default(0);
            $table->string('step_code', 40)->nullable();
            $table->unsignedSmallInteger('tier70_count')->default(0);
            $table->string('tier70_code', 40)->nullable();
            $table->unsignedSmallInteger('tier50_count')->default(0);
            $table->string('tier50_code', 40)->nullable();
            $table->unsignedSmallInteger('row_count')->default(0);
        });

        Schema::create('bae_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('order_number', 50)->nullable();
            $table->date('order_date')->nullable();
            $table->string('contract', 100)->nullable();
            $table->foreignId('battery_model_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mounting', 10)->nullable();
            $table->unsignedInteger('capacity_ah')->nullable();
            $table->string('technology', 20)->nullable();
            $table->unsignedTinyInteger('battery_count')->default(1);
            $table->unsignedTinyInteger('cells_per_battery')->default(24);
            $table->decimal('volts_per_cell', 5, 2)->default(2);
            $table->decimal('cell_price', 10, 2)->nullable();
            // Per order line {"code": …, "per_battery": …}; the rack {"code": …, "price": …}.
            $table->json('lines')->nullable();
            $table->string('delivery_date', 100)->nullable();
            $table->string('delivery_address')->nullable();
            $table->string('contact_name', 100)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->string('contact_fax', 50)->nullable();
            $table->string('contact_mobile', 50)->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->text('billing_block')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $this->seed();
    }

    /**
     * The Excel "Batterijen" table: [model, mounting, rack price, intercell count, code, step count, code,
     * tier 70 count, code, tier 50 count, code, row count]. Model names are compared without spaces or case.
     */
    protected function seed(): void
    {
        $rows = [
            ['4opzv200', 'lijn', 190, 22, null, 0, null, 2, '4437251 (70x1250)', 0, null, 1],
            ['4opzv200', 'u_vorm', 192, 22, null, 1, null, 2, '4437251 (70x1250)', 0, null, 0],
            ['4opzv200', 'trap', 265, 22, null, 1, null, 2, '4437251 (70x1250)', 0, null, 0],
            ['4opzv200', 'kast', null, 16, null, 6, null, 2, '4437251 (70x1250)', 1, null, 0],
            ['6opzv420', 'lijn', 257, 22, null, 0, null, 2, '4437251 (70x1250)', 0, null, 1],
            ['6opzv420', 'u_vorm', 262, 22, null, 1, null, 2, '4437251 (70x1250)', 0, null, 0],
            ['6opzv420', 'trap', 277, 22, '4364116 (90x155)', 1, '4435350 (50x350)', 2, '4437251 (70x1250)', 0, null, 0],
            ['6opzv420', 'kast', null, 16, '4364116 (90x155)', 6, '4435350 (50x350)', 2, '4437251 (70x1250)', 1, '4435251 (50x1250)', 0],
            ['7opzv490', 'lijn', 255, 22, null, 0, null, 2, '4437251 (70x1250)', 0, null, 1],
            ['7opzv490', 'u_vorm', 281, 22, '4364117 (90x175)', 1, '4435450 (50x450)', 2, '4437251 (70x1250)', 0, null, 0],
            ['7opzv490', 'trap', 210, 22, null, 1, null, 2, '4437251 (70x1250)', 0, null, 0],
            ['7opzv490', 'kast', null, 16, '4364117 (90x175)', 6, '4435350 (50x350)', 2, '4437251 (70x1250)', 1, '4435251 (50x1250)', 0],
            ['6opzv600', 'lijn', 257, 22, null, 0, null, 2, '4437251 (70x1250)', 0, null, 1],
            ['6opzv600', 'u_vorm', 262, 22, '4364116 (90x155)', 1, '4435350 (50x350)', 2, '4437251 (70x1250)', 0, null, 0],
            ['6opzv600', 'trap', 277, 22, '4364116 (90x155)', 1, '4435350 (50x350)', 2, '4437251 (70x1250)', 0, null, 0],
            ['6opzv600', 'kast', null, 16, '4364116 (90x155)', 6, '4435350 (50x350)', 2, '4437251 (70x1250)', 1, '4435251 (50x1250)', 0],
            ['8opzv800', 'lijn', 371, 44, null, 0, null, 4, '4437251 (70x1250)', 0, null, 2],
            ['8opzv800', 'u_vorm', 367, 44, '4364787 (90x113)', 2, '4435350 (50x350)', 4, '4437251 (70x1250)', 0, null, 0],
            ['8opzv800', 'trap', 386, 44, '4364787 (90x113)', 2, '4435350 (50x350)', 4, '4437251 (70x1250)', 0, null, 0],
            ['8opzv800', 'kast', null, 32, '4364787 (90x113)', 12, '4435350 (50x350)', 4, '4437251 (70x1250)', 2, '4435251 (50x1250)', 0],
            ['10opzv1000', 'lijn', 433, 44, null, 0, null, 4, '4437251 (70x1250)', 0, null, 2],
            ['10opzv1000', 'u_vorm', 379, 44, '4364787 (90x113)', 2, '4435400 (50x400)', 4, '4437251 (70x1250)', 0, null, 0],
            ['10opzv1000', 'trap', 413, 44, '4364787 (90x113)', 2, '4435400 (50x400)', 4, '4437251 (70x1250)', 0, null, 0],
            ['10opzv1000', 'kast', null, 44, null, 2, null, 4, '4437251 (70x1250)', 0, null, 0],
            ['12opzv1200', 'lijn', 433, 44, null, 0, null, 4, '4437251 (70x1250)', 0, null, 2],
            ['12opzv1200', 'u_vorm', 379, 44, '4364787 (90x113)', 2, '4435350 (50x350)', 4, '4437251 (70x1250)', 0, null, 0],
            ['12opzv1200', 'trap', 413, 44, '4364787 (90x113)', 2, '4435350 (50x350)', 4, '4437251 (70x1250)', 0, null, 0],
            ['12opzv1200', 'kast', null, 44, null, 2, null, 4, '4437251 (70x1250)', 0, null, 0],
            ['12opzv1500', 'lijn', 433, 46, '4364787 (90x113)', 0, null, 4, '4437251 (70x1250)', 0, null, 0],
            ['12opzv1500', 'u_vorm', 379, 44, '4364787 (90x113)', 2, '4435350 (50x350)', 4, '4437251 (70x1250)', 0, null, 0],
            ['12opzv1500', 'trap', 413, 44, '4364787 (90x113)', 2, '4435400 (50x400)', 4, '4437251 (70x1250)', 0, null, 0],
            ['12opzv1500', 'kast', null, 44, null, 2, null, 4, '4437251 (70x1250)', 0, null, 0],
            ['16opzv2000', 'lijn', 328, 66, null, 0, null, 6, '4437251 (70x1250)', 0, null, 3],
            ['16opzv2000', 'u_vorm', 545, 66, '4364788 (90x117)', 3, '4437500 (70x500)', 6, '4437251 (70x1250)', 0, null, 0],
            ['16opzv2000', 'trap', 756, 66, '4364788 (90x117)', 3, '4437550 (70x550)', 3, '4437251 (70x1250)', 3, '4437700 (70x700)', 0],
            ['16opzv2000', 'kast', null, 66, null, 3, null, 6, '4437251 (70x1250)', 0, null, 0],
            ['20opzv2500', 'lijn', 713, 88, null, 0, null, 8, '4437251 (70x1250)', 0, null, 4],
            ['20opzv2500', 'u_vorm', 636, 88, null, 4, null, 8, '4437251 (70x1250)', 0, null, 0],
            ['20opzv2500', 'trap', 634, 88, null, 4, null, 8, '4437251 (70x1250)', 0, null, 0],
            ['20opzv2500', 'kast', null, 88, null, 4, null, 8, '4437251 (70x1250)', 0, null, 0],
            ['4opzs200', 'lijn', 257, 22, null, 0, null, 2, '4437251 (70x1250)', 0, null, 1],
            ['4opzs200', 'u_vorm', 257, 22, null, 1, null, 2, '4437251 (70x1250)', 0, null, 0],
            ['4opzs200', 'trap', 257, 22, null, 1, null, 2, '4437251 (70x1250)', 0, null, 0],
            ['4opzs200', 'kast', null, 22, null, 1, null, 2, '4437251 (70x1250)', 0, null, 0],
            ['6opzs420', 'lijn', 257, 22, null, 0, null, 2, '4437251 (70x1250)', 0, null, 1],
            ['6opzs420', 'u_vorm', 262, 22, null, 1, null, 2, '4437251 (70x1250)', 0, null, 0],
            ['6opzs420', 'trap', 277, 22, null, 1, null, 2, '4437251 (70x1250)', 0, null, 0],
            ['6opzs420', 'kast', null, 22, null, 1, null, 2, '4437251 (70x1250)', 0, null, 0],
            ['7opzs490', 'lijn', 257, 22, null, 0, null, 2, '4437251 (70x1250)', 0, null, 1],
            ['7opzs490', 'u_vorm', 257, 22, null, 1, null, 2, '4437251 (70x1250)', 0, null, 0],
            ['7opzs490', 'trap', 257, 22, null, 1, null, 2, '4437251 (70x1250)', 0, null, 0],
            ['7opzs490', 'kast', null, 22, null, 1, null, 2, '4437251 (70x1250)', 0, null, 0],
            ['6opzs600', 'lijn', 257, 22, null, 0, null, 2, '4437251 (70x1250)', 0, null, 1],
            ['6opzs600', 'u_vorm', 262, 22, null, 1, null, 2, '4437251 (70x1250)', 0, null, 0],
            ['6opzs600', 'trap', 277, 22, null, 1, null, 2, '4437251 (70x1250)', 0, null, 0],
            ['6opzs600', 'kast', null, 22, null, 1, null, 2, '4437251 (70x1250)', 0, null, 0],
            ['8opzs800', 'lijn', 371, 44, null, 0, null, 4, '4437251 (70x1250)', 0, null, 2],
            ['8opzs800', 'u_vorm', 367, 44, null, 2, null, 4, '4437251 (70x1250)', 0, null, 0],
            ['8opzs800', 'trap', 386, 44, null, 2, null, 4, '4437251 (70x1250)', 0, null, 0],
            ['8opzs800', 'kast', null, 44, null, 2, null, 4, '4437251 (70x1250)', 0, null, 0],
            ['10opzs1000', 'lijn', 433, 44, null, 0, null, 4, '4437251 (70x1250)', 0, null, 2],
            ['10opzs1000', 'u_vorm', 379, 44, null, 2, null, 4, '4437251 (70x1250)', 0, null, 0],
            ['10opzs1000', 'trap', 413, 44, null, 2, null, 4, '4437251 (70x1250)', 0, null, 0],
            ['10opzs1000', 'kast', null, 44, null, 2, null, 4, '4437251 (70x1250)', 0, null, 0],
            ['12opzs1200', 'lijn', 433, 44, null, 0, null, 4, '4437251 (70x1250)', 0, null, 2],
            ['12opzs1200', 'u_vorm', 379, 44, null, 2, null, 4, '4437251 (70x1250)', 0, null, 0],
            ['12opzs1200', 'trap', 413, 44, null, 2, null, 4, '4437251 (70x1250)', 0, null, 0],
            ['12opzs1200', 'kast', null, 44, null, 2, null, 4, '4437251 (70x1250)', 0, null, 0],
            ['12opzs1500', 'lijn', 433, 44, null, 0, null, 4, '4437251 (70x1250)', 0, null, 2],
            ['12opzs1500', 'u_vorm', 379, 44, null, 2, null, 4, '4437251 (70x1250)', 0, null, 0],
            ['12opzs1500', 'trap', 413, 44, null, 2, null, 4, '4437251 (70x1250)', 0, null, 0],
            ['12opzs1500', 'kast', null, 44, null, 2, null, 4, '4437251 (70x1250)', 0, null, 0],
            ['16opzs2000', 'lijn', 691, 66, null, 0, null, 6, '4437251 (70x1250)', 0, null, 3],
            ['16opzs2000', 'u_vorm', 545, 66, null, 3, null, 6, '4437251 (70x1250)', 0, null, 0],
            ['16opzs2000', 'trap', 756, 66, null, 3, null, 6, '4437251 (70x1250)', 0, null, 0],
            ['16opzs2000', 'kast', null, 66, null, 3, null, 6, '4437251 (70x1250)', 0, null, 0],
            ['20opzs2500', 'lijn', 713, 88, null, 0, null, 8, '4437251 (70x1250)', 0, null, 4],
            ['20opzs2500', 'u_vorm', 636, 88, '4364787 (90x113)', 4, '4435600 (50x600)', 8, '4437251 (70x1250)', 0, null, 0],
            ['20opzs2500', 'trap', 634, 88, null, 4, null, 8, '4437251 (70x1250)', 0, null, 0],
            ['20opzs2500', 'kast', null, 88, null, 4, null, 8, '4437251 (70x1250)', 0, null, 0],
            ['24opzs3000', 'lijn', 541, 88, null, 0, null, 8, '4437251 (70x1250)', 0, null, 4],
            ['24opzs3000', 'u_vorm', 541, 88, null, 4, null, 8, '4437251 (70x1250)', 0, null, 0],
            ['24opzs3000', 'trap', 541, 88, null, 4, null, 8, '4437251 (70x1250)', 0, null, 0],
            ['24opzs3000', 'kast', null, 88, null, 4, null, 8, '4437251 (70x1250)', 0, null, 0],
        ];

        $models = DB::table('battery_models')->pluck('id', 'name')
            ->mapWithKeys(fn (int $id, string $name): array => [strtolower(preg_replace('/\s+/', '', $name)) => $id]);

        foreach ($rows as [$model, $mounting, $price, $intercell, $intercellCode, $step, $stepCode, $tier70, $tier70Code, $tier50, $tier50Code, $row]) {
            if (! $id = $models[$model] ?? null) {
                continue;
            }

            DB::table('battery_racks')->where('battery_model_id', $id)->where('mounting', $mounting)->update([
                'bae_price' => $price,
                'intercell_count' => $intercell, 'intercell_code' => $intercellCode,
                'step_count' => $step, 'step_code' => $stepCode,
                'tier70_count' => $tier70, 'tier70_code' => $tier70Code,
                'tier50_count' => $tier50, 'tier50_code' => $tier50Code,
                'row_count' => $row,
            ]);
        }

        // The only BAE cell price in the Excel.
        if ($id = $models['7opzv490'] ?? null) {
            DB::table('battery_models')->where('id', $id)->update(['bae_cell_price' => 214.52]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bae_orders');

        Schema::table('battery_racks', function (Blueprint $table) {
            $table->dropColumn(['bae_price', 'intercell_count', 'intercell_code', 'step_count', 'step_code', 'tier70_count', 'tier70_code', 'tier50_count', 'tier50_code', 'row_count']);
        });

        Schema::table('battery_models', function (Blueprint $table) {
            $table->dropColumn('bae_cell_price');
        });
    }
};
