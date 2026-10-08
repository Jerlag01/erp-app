<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SuggestionType: string implements HasColor, HasLabel
{
    case Idea = 'idea';

    case Complaint = 'complaint';

    case Other = 'other';

    public function getLabel(): string
    {
        return __('erp.enums.suggestion_type.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Idea => 'success',
            self::Complaint => 'danger',
            self::Other => 'gray',
        };
    }
}
