<?php

namespace App\Filament\Admin\Resources\MailTemplates\Pages;

use App\Filament\Admin\Resources\MailTemplates\MailTemplateResource;
use App\Support\MailTemplates;
use Filament\Resources\Pages\ManageRecords;

class ManageMailTemplates extends ManageRecords
{
    protected static string $resource = MailTemplateResource::class;

    /**
     * Every system e-mail is listed in every language, also ones added in a later version.
     */
    public function mount(): void
    {
        MailTemplates::sync();

        parent::mount();
    }

    public function getSubheading(): ?string
    {
        return __('erp.mail_templates.intro');
    }
}
