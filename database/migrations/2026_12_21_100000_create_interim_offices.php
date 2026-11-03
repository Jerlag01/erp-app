<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Interim offices with their contacts (name and e-mail). An interim employee is linked to one office; its
 * contacts also get the weekly prestation sheets of that employee. The free-text offices filled in so far
 * become offices (without contacts) and stay linked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interim_offices', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->boolean('is_active')->default(true);
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('interim_office_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interim_office_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('email', 190);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('interim_office_id')->nullable()->after('interim_agency')->constrained()->nullOnDelete();
        });

        $names = DB::table('employees')->whereNotNull('interim_agency')->where('interim_agency', '!=', '')->distinct()->pluck('interim_agency');

        foreach ($names as $name) {
            $name = trim((string) $name);
            $id = DB::table('interim_offices')->where('name', $name)->value('id')
                ?? DB::table('interim_offices')->insertGetId(['name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('employees')->where('interim_agency', $name)->update(['interim_office_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('interim_office_id');
        });
        Schema::dropIfExists('interim_office_contacts');
        Schema::dropIfExists('interim_offices');
    }
};
