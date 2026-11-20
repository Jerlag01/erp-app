<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Certificate types become a list that can be managed (like in Jacobs ERP), filled with the fixed ones.
 * Certificates keep pointing to their type by its code, so nothing else moves. A type with a validity
 * (months) gives its certificates their expiry date from the date obtained; without one it never expires.
 * The fixed types start without a validity: existing expiry dates stay until the type gets one.
 */
return new class extends Migration
{
    /** The fixed types, in their order: [code, overview, shows validity, kind]. */
    protected const TYPES = [
        ['vca_vol', 'always', true, null], ['vca', 'always', true, null], ['prevention_advisor_3', 'always', true, null], ['nuclear', 'always', true, null],
        ['proximus', 'always', true, null], ['charging_stations', 'always', false, null], ['ba4', 'always', false, null], ['ba5', 'always', false, null],
        ['first_aid_fire', 'always', false, null], ['first_aid', 'always', true, null], ['scissor_lift', 'always', true, null], ['aerial_work_platform', 'always', true, null],
        ['forklift', 'always', true, null], ['ladders', 'always', true, null], ['work_at_height_module_1', 'always', true, null], ['work_at_height_module_2', 'always', true, null],
        ['loader_crane', 'always', false, null], ['driver_code_95', 'always', true, null],
        ['prevention_advisor_1', 'held', true, null], ['prevention_advisor_2', 'held', true, null], ['driving_licence', 'held', false, 'driving_licence'],
        ['work_at_height', 'held', true, null], ['safety_harness', 'held', true, null], ['school_degree', 'never', true, 'school_degree'],
    ];

    public function up(): void
    {
        Schema::create('certificate_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name_nl', 150)->nullable();
            $table->string('name_fr', 150)->nullable();
            $table->string('name_en', 150)->nullable();
            $table->string('short_nl', 60)->nullable();
            $table->string('short_fr', 60)->nullable();
            $table->string('short_en', 60)->nullable();
            $table->string('overview', 10)->default('held');
            $table->boolean('shows_validity')->default(true);
            $table->unsignedSmallInteger('validity_months')->nullable();
            $table->string('kind', 30)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $text = function (string $key, string $locale): ?string {
            $value = trans($key, [], $locale);

            return $value === $key ? null : $value;
        };

        foreach (self::TYPES as $index => [$code, $overview, $showsValidity, $kind]) {
            DB::table('certificate_types')->insert([
                'code' => $code,
                ...collect(['nl', 'fr', 'en'])->flatMap(fn (string $locale): array => [
                    "name_{$locale}" => $text("erp.enums.certificate_type.{$code}", $locale) ?? $code,
                    "short_{$locale}" => $text("erp.training_matrix.columns.{$code}", $locale),
                ])->all(),
                'overview' => $overview,
                'shows_validity' => $showsValidity,
                'kind' => $kind,
                'sort' => ($index + 1) * 10,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_types');
    }
};
