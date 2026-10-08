<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * How a knowledge base article is written: with the rich text editor or in Markdown.
 */
enum KbFormat: string implements HasIcon, HasLabel
{
    case RichText = 'rich';

    case Markdown = 'markdown';

    public function getLabel(): string
    {
        return __('erp.enums.kb_format.'.$this->value);
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::RichText => Heroicon::OutlinedDocumentText,
            self::Markdown => Heroicon::OutlinedHashtag,
        };
    }
}
