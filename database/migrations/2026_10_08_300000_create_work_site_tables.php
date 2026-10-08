<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Codes in ICU's region list that are not countries (EU, UN, …) or not ISO 3166-1 countries.
     */
    private const SKIP = ['EU', 'EZ', 'QO', 'UN', 'XA', 'XB', 'ZZ', 'AC', 'CP', 'CQ', 'DG', 'EA', 'IC', 'TA'];

    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->char('code', 2)->unique();
            $table->json('name');
            $table->timestamps();
        });

        $this->seedCountries();

        Schema::create('work_site_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 60);
            $table->string('last_name', 60);
            $table->string('company', 120)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('work_site_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->foreignId('work_site_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('work_sites', function (Blueprint $table) {
            $table->id();
            $table->string('cow_code', 10)->unique();
            $table->string('building_type', 20)->index();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('street', 150)->nullable();
            $table->string('house_number', 20)->nullable();
            $table->string('addition', 20)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->foreignId('work_site_area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_site_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->json('images')->nullable();
            $table->timestamps();
        });

        Schema::create('work_site_remarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_site_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('work_site_type_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_site_id')->constrained()->cascadeOnDelete();
            $table->string('from_type', 20)->nullable();
            $table->string('to_type', 20);
            $table->date('changed_on');
            $table->string('reason', 150)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_site_type_changes');
        Schema::dropIfExists('work_site_remarks');
        Schema::dropIfExists('work_sites');
        Schema::dropIfExists('work_site_areas');
        Schema::dropIfExists('work_site_contacts');
        Schema::dropIfExists('countries');
    }

    /**
     * Every country with its name in Dutch, French and English, taken from PHP's intl data.
     */
    private function seedCountries(): void
    {
        $now = now();
        $rows = [];

        foreach (ResourceBundle::create('en', 'ICUDATA-region')->get('Countries') as $code => $englishName) {
            if (! preg_match('/^[A-Z]{2}$/', $code) || in_array($code, self::SKIP, true)) {
                continue;
            }

            $rows[] = [
                'code' => $code,
                'name' => json_encode([
                    'nl' => Locale::getDisplayRegion("-{$code}", 'nl'),
                    'fr' => Locale::getDisplayRegion("-{$code}", 'fr'),
                    'en' => Locale::getDisplayRegion("-{$code}", 'en'),
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('countries')->insert($rows);
    }
};
