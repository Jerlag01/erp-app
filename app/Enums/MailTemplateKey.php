<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Every e-mail the system sends, each with an editable template per language.
 */
enum MailTemplateKey: string implements HasLabel
{
    case WelcomeEmployee = 'welcome_employee';

    case InvoiceSent = 'invoice_sent';

    case CreditNoteSent = 'credit_note_sent';

    case PaymentReminder1 = 'payment_reminder_1';

    case PaymentReminder2 = 'payment_reminder_2';

    case PaymentReminder3 = 'payment_reminder_3';

    public function getLabel(): string
    {
        return __('erp.enums.mail_template.'.$this->value);
    }

    /**
     * Placeholders available in this template, e.g. {client_name}.
     *
     * @return list<string>
     */
    public function placeholders(): array
    {
        $company = ['company_name', 'site_name'];

        return match ($this) {
            self::WelcomeEmployee => [...$company, 'name', 'email', 'login_url', 'password_link', 'link_days'],
            self::InvoiceSent, self::CreditNoteSent => [...$company, 'client_name', 'number', 'amount', 'issue_date', 'due_date', 'structured_reference', 'iban', 'subject'],
            self::PaymentReminder1, self::PaymentReminder2, self::PaymentReminder3 => [...$company, 'client_name', 'number', 'amount', 'issue_date', 'due_date', 'days_overdue', 'structured_reference', 'iban'],
        };
    }

    public static function reminder(int $level): self
    {
        return match ($level) {
            1 => self::PaymentReminder1,
            2 => self::PaymentReminder2,
            default => self::PaymentReminder3,
        };
    }
}
