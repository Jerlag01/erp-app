<?php

namespace App\Actions;

use App\Models\OrderReference;
use App\Models\OrderReferenceCounter;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The "+1" button: takes the next number of the current year and builds the reference
 * from today's month and year and the user's initials, e.g. "466/1026/JL".
 */
class GenerateOrderReference
{
    /**
     * @param  array{supplier?: string|null, project?: string|null, description?: string|null}  $details
     */
    public function handle(User $user, array $details = []): OrderReference
    {
        $now = now();

        return DB::transaction(function () use ($user, $details, $now): OrderReference {
            DB::table('order_reference_counters')->insertOrIgnore([
                'year' => $now->year,
                'last_number' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // The row lock makes simultaneous presses wait for each other, so numbers never repeat.
            $counter = OrderReferenceCounter::query()->whereKey($now->year)->lockForUpdate()->firstOrFail();
            $number = $counter->last_number + 1;
            $counter->update(['last_number' => $number]);

            $initials = $user->referenceInitials();

            return OrderReference::create([
                'year' => $now->year,
                'number' => $number,
                'month' => $now->month,
                'initials' => $initials,
                'reference' => OrderReference::format($number, $now->month, $now->year, $initials),
                'user_id' => $user->id,
                'supplier' => $details['supplier'] ?? null,
                'project' => $details['project'] ?? null,
                'description' => $details['description'] ?? null,
            ]);
        });
    }

    /**
     * Admins can move the counter, e.g. to continue from the old order log.
     * The next reference then gets $lastNumber + 1.
     */
    public function setCounter(int $year, int $lastNumber): void
    {
        $highestUsed = (int) OrderReference::query()->where('year', $year)->max('number');

        OrderReferenceCounter::query()->updateOrCreate(['year' => $year], ['last_number' => max($lastNumber, $highestUsed)]);
    }

    /**
     * Withdraws the latest reference of its year (a "-1"), so its number is handed out again.
     */
    public function withdraw(OrderReference $reference): void
    {
        DB::transaction(function () use ($reference): void {
            $counter = OrderReferenceCounter::query()->whereKey($reference->year)->lockForUpdate()->first();

            if (! $reference->isLatestOfYear()) {
                return;
            }

            $reference->delete();

            if ($counter && $counter->last_number === $reference->number) {
                $counter->update(['last_number' => $reference->number - 1]);
            }
        });
    }
}
