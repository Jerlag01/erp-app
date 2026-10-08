<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('is_guest')->default(false)->after('is_admin');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('initials', 4)->nullable()->after('name');
            $table->decimal('vacation_days', 4, 1)->default(20)->after('is_active');
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name', 100);
            $table->timestamps();
        });

        Schema::create('vacation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('half_day')->default(false);
            $table->decimal('days', 4, 1);
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'start_date']);
        });

        Schema::create('order_reference_counters', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('order_references', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('number');
            $table->unsignedTinyInteger('month');
            $table->string('initials', 4);
            $table->string('reference', 30)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier', 150)->nullable();
            $table->string('project', 150)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['year', 'number']);
        });

        DB::table('roles')->insertOrIgnore([
            'name' => 'Gast / Magazijn',
            'description' => 'Medewerkers zonder ploeg: geen rechten.',
            'is_admin' => false,
            'is_guest' => true,
            'permissions' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('order_references');
        Schema::dropIfExists('order_reference_counters');
        Schema::dropIfExists('vacation_requests');
        Schema::dropIfExists('holidays');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['initials', 'vacation_days']);
        });

        DB::table('roles')->where('is_guest', true)->delete();

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('is_guest');
        });
    }
};
