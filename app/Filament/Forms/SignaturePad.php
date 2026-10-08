<?php

namespace App\Filament\Forms;

use Closure;
use Filament\Forms\Components\Field;

/**
 * Draw a signature with mouse, finger or pen. The state is a PNG data URL.
 */
class SignaturePad extends Field
{
    /**
     * Largest accepted signature image (a drawn signature is usually 10–40 kB).
     */
    public const MAX_BYTES = 400_000;

    protected string $view = 'filament.forms.signature-pad';

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
            if (blank($value)) {
                return;
            }

            if (! is_string($value) || ! str_starts_with($value, 'data:image/png;base64,') || strlen($value) > self::MAX_BYTES) {
                $fail(__('erp.finance.expenses.signature_invalid'));

                return;
            }

            $png = base64_decode(substr($value, strlen('data:image/png;base64,')), true);

            if ($png === false || ! str_starts_with($png, "\x89PNG")) {
                $fail(__('erp.finance.expenses.signature_invalid'));
            }
        });
    }
}
