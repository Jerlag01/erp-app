<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A ZIP for the accountant: the sales and purchase documents of a period (UBL and PDF) with the
 * journals as CSV. Kept so it can be downloaded or sent again.
 */
#[Fillable(['period_from', 'period_to', 'parts', 'sales_count', 'purchase_count', 'path', 'size', 'emailed_to', 'emailed_at', 'created_by'])]
class AccountingExport extends Model
{
    public const DISK = 'local';

    public const SALES = 'sales';

    public const PURCHASES = 'purchases';

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function filename(): string
    {
        return 'boekhouding-'.$this->period_from->format('Ymd').'-'.$this->period_to->format('Ymd').'.zip';
    }

    protected static function booted(): void
    {
        static::deleted(function (self $export): void {
            Storage::disk(self::DISK)->delete($export->path);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'parts' => 'array',
            'emailed_at' => 'datetime',
        ];
    }
}
