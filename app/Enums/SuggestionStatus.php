<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SuggestionStatus: string implements HasColor, HasLabel
{
    case New = 'new';

    case InProgress = 'in_progress';

    case Done = 'done';

    public function getLabel(): string
    {
        return __('erp.enums.suggestion_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::InProgress => 'info',
            self::Done => 'success',
        };
    }
}
