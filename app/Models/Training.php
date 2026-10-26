<?php

namespace App\Models;

use App\Models\Concerns\SyncsToOutlook;
use Carbon\CarbonInterface;
use Database\Factories\TrainingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A training planned for employees: which training, where (address), given by which company and
 * its contact details. Like vacation, a training day blocks the planning and counts as a worked day
 * (8 hours) on the prestation sheet.
 */
#[Fillable([
    'name', 'start_date', 'end_date', 'company', 'street', 'house_number', 'addition', 'postal_code', 'city',
    'contact_phone', 'contact_email', 'notes', 'created_by',
])]
class Training extends Model
{
    /** @use HasFactory<TrainingFactory> */
    use HasFactory;

    use SyncsToOutlook;

    /**
     * @return BelongsToMany<User, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<Training>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface|string $from, CarbonInterface|string $until): void
    {
        $from = is_string($from) ? $from : $from->toDateString();
        $until = is_string($until) ? $until : $until->toDateString();

        $query->whereDate('start_date', '<=', $until)->whereDate('end_date', '>=', $from);
    }

    public function addressLine(): ?string
    {
        $street = trim(implode(' ', array_filter([$this->street, $this->house_number, $this->addition])));
        $place = trim(implode(' ', array_filter([$this->postal_code, $this->city])));

        return implode(', ', array_filter([$street, $place])) ?: null;
    }

    /**
     * "Opleiding: VCA – Securex, Gent".
     */
    public function note(): string
    {
        return 'Opleiding: '.$this->name.(filled($this->company) || filled($this->city) ? ' – '.implode(', ', array_filter([$this->company, $this->city])) : '');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
