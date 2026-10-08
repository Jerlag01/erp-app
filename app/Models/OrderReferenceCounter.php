<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The last order number handed out per year; a new year starts again at 001.
 */
#[Fillable(['year', 'last_number'])]
class OrderReferenceCounter extends Model
{
    protected $primaryKey = 'year';

    public $incrementing = false;

    protected $keyType = 'int';

    public static function lastNumber(int $year): int
    {
        return (int) static::query()->whereKey($year)->value('last_number');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_number' => 'integer',
        ];
    }
}
