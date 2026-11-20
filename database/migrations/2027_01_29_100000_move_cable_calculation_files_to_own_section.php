<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cable calculation PDFs get their own tab on the project: the ones kept in the extra files move there
 * (the stored files stay where they are; only their section changes).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('project_files')
            ->where('section', 'extra')
            ->whereIn('id', DB::table('cable_calculations')->whereNotNull('project_file_id')->select('project_file_id'))
            ->update(['section' => 'cable_calculations']);
    }

    public function down(): void
    {
        DB::table('project_files')->where('section', 'cable_calculations')->update(['section' => 'extra']);
    }
};
