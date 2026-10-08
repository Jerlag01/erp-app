<?php

namespace App\Models;

use App\Enums\ExpenseStatus;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;

/**
 * An incoming invoice (or credit note) from a supplier, entered by hand or received over Peppol.
 * It only counts as accepted after someone with the "validate" right accepted it with a note
 * and their signature; the date is set by the system.
 */
#[Fillable([
    'supplier_name', 'supplier_vat', 'supplier_invoice_number', 'is_credit_note', 'invoice_date', 'due_date',
    'subtotal', 'vat_total', 'total', 'description', 'project_id', 'document', 'document_name', 'source', 'ubl_path',
    'paid_on', 'created_by',
])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    public const DISK = 'local';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'source' => 'manual',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function isPending(): bool
    {
        return $this->status === ExpenseStatus::Pending;
    }

    /**
     * Accepts the expense. A note and a signature are both required; the date is now.
     *
     * @param  string  $signature  PNG data URL from the signature pad
     */
    public function accept(User $user, string $note, string $signature): void
    {
        if (trim($note) === '' || ! str_starts_with($signature, 'data:image/png;base64,')) {
            throw new LogicException('Accepting an expense needs a note and a signature.');
        }

        $png = base64_decode(substr($signature, strlen('data:image/png;base64,')), true);

        if ($png === false || ! str_starts_with($png, "\x89PNG")) {
            throw new LogicException('The signature is not a valid PNG image.');
        }

        $path = 'expenses/signatures/'.$this->id.'-'.Str::random(12).'.png';
        Storage::disk(self::DISK)->put($path, $png);

        $this->forceFill([
            'status' => ExpenseStatus::Accepted,
            'validated_by' => $user->id,
            'validated_at' => now(),
            'validation_note' => trim($note),
            'signature_path' => $path,
        ])->save();
    }

    public function reject(User $user, string $note): void
    {
        $this->forceFill([
            'status' => ExpenseStatus::Rejected,
            'validated_by' => $user->id,
            'validated_at' => now(),
            'validation_note' => trim($note),
            'signature_path' => null,
        ])->save();
    }

    /**
     * The amount with its sign: a supplier's credit note lowers the expenses.
     */
    public function signedTotal(bool $withVat): float
    {
        return ($this->is_credit_note ? -1 : 1) * (float) ($withVat ? $this->total : $this->subtotal);
    }

    protected static function booted(): void
    {
        static::saving(function (self $expense): void {
            $expense->supplier_vat = Client::normaliseVat($expense->supplier_vat);

            if ($expense->isDirty(['subtotal', 'vat_total'])) {
                $expense->total = round((float) $expense->subtotal + (float) $expense->vat_total, 2);
            }
        });

        static::deleted(function (self $expense): void {
            Storage::disk(self::DISK)->delete(array_filter([$expense->document, $expense->ubl_path, $expense->signature_path]));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ExpenseStatus::class,
            'is_credit_note' => 'boolean',
            'invoice_date' => 'date',
            'due_date' => 'date',
            'paid_on' => 'date',
            'validated_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }
}
