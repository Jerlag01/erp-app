<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Offers become a module of their own, with or without finance: a client name for offers without
 * the finance clients, and the offer settings move from "finance.offer_*" to their own "offers" group.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->string('client_name')->nullable()->after('client_id');
        });

        $finance = DB::table('settings')->where('key', 'finance')->value('value');
        $finance = $finance ? (array) json_decode($finance, true) : [];
        $offers = [];

        foreach ($finance as $key => $value) {
            if (str_starts_with($key, 'offer_')) {
                $offers[substr($key, 6)] = $value;
                unset($finance[$key]);
            }
        }

        if ($offers !== []) {
            DB::table('settings')->where('key', 'finance')->update(['value' => json_encode($finance)]);
            DB::table('settings')->updateOrInsert(['key' => 'offers'], ['value' => json_encode($offers), 'updated_at' => now(), 'created_at' => now()]);
        }

        Cache::forget('erp-settings');
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('client_name');
        });
    }
};
