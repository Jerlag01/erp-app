<?php

use App\Enums\ProjectNumbering;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Project numbers get 4 digits (604-0923-83HVR); yearly numbers the year and 4 digits (605-260012-…)
 * instead of the year and 3 (26012), so they are renumbered. Every project reference is rebuilt, and
 * waste lines linked to a project follow.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->convert(fn (int $number): int => intdiv($number, 1000) * ProjectNumbering::PER_YEAR + $number % 1000, 4, 6);
    }

    public function down(): void
    {
        $this->convert(fn (int $number): int => intdiv($number, ProjectNumbering::PER_YEAR) * 1000 + $number % ProjectNumbering::PER_YEAR, 3, 5);
    }

    /**
     * @param  callable(int): int  $yearly  how a yearly number changes
     */
    protected function convert(callable $yearly, int $digits, int $yearlyDigits): void
    {
        DB::transaction(function () use ($yearly, $digits, $yearlyDigits): void {
            $categories = DB::table('project_categories')->get(['id', 'code', 'numbering', 'next_number'])->keyBy('id');

            foreach ($categories as $category) {
                if ($category->numbering !== ProjectNumbering::Yearly->value) {
                    continue;
                }

                // Highest first, so no number meets one that is still to be moved (unique per category).
                foreach (DB::table('projects')->where('project_category_id', $category->id)->orderByDesc('number')->get(['id', 'number']) as $project) {
                    DB::table('projects')->where('id', $project->id)->update(['number' => $yearly((int) $project->number)]);
                }

                if ($category->next_number !== null) {
                    DB::table('project_categories')->where('id', $category->id)->update(['next_number' => $yearly((int) $category->next_number)]);
                }
            }

            DB::table('projects')->orderBy('id')->chunkById(500, function ($projects) use ($categories, $digits, $yearlyDigits): void {
                foreach ($projects as $project) {
                    $category = $categories[$project->project_category_id] ?? null;

                    if (! $category) {
                        continue;
                    }

                    $numbering = ProjectNumbering::tryFrom((string) $category->numbering) ?? ProjectNumbering::Sequence;
                    $reference = implode('-', array_filter([
                        $category->code,
                        str_pad((string) $project->number, $numbering === ProjectNumbering::Yearly ? $yearlyDigits : $digits, '0', STR_PAD_LEFT),
                        $numbering->usesWorkSite() ? $project->cow_code : null,
                    ]));

                    if ($reference !== $project->reference) {
                        DB::table('projects')->where('id', $project->id)->update(['reference' => $reference]);
                    }
                }
            }, 'id');

            // Waste lines linked to a project show its (new) reference.
            DB::table('waste_entry_lines')->whereNotNull('project_id')->orderBy('id')->each(function ($line): void {
                $reference = DB::table('projects')->where('id', $line->project_id)->value('reference');

                if ($reference !== null && $reference !== $line->project_reference) {
                    DB::table('waste_entry_lines')->where('id', $line->id)->update(['project_reference' => $reference]);
                }
            });
        });
    }
};
