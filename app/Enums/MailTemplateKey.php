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

    case PlanningCreated = 'planning_created';

    case PlanningUpdated = 'planning_updated';

    case PlanningDeleted = 'planning_deleted';

    case TimesheetWeek = 'timesheet_week';

    case TimesheetEmployee = 'timesheet_employee';

    case ClientWelcome = 'client_welcome';

    case ClientPasswordReset = 'client_password_reset';

    case GpwRequest = 'gpw_request';

    case GpwApproved = 'gpw_approved';

    case GpwRejected = 'gpw_rejected';

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
            self::PlanningCreated, self::PlanningDeleted => [...$company, 'name', 'changed_by', 'items', 'planning_url'],
            self::PlanningUpdated => [...$company, 'name', 'changed_by', 'items', 'previous', 'planning_url'],
            self::TimesheetWeek => [...$company, 'week', 'from', 'until', 'count', 'summary'],
            self::TimesheetEmployee => [...$company, 'name', 'week', 'from', 'until', 'hours', 'overtime', 'balance', 'km'],
            self::ClientWelcome => [...$company, 'name', 'email', 'company', 'portal_name', 'portal_url', 'password_link', 'link_days', 'overviews'],
            self::GpwRequest => [...$company, 'contact_name', 'project', 'cow_code', 'address', 'description', 'period', 'from', 'until', 'remarks', 'requested_by', 'requester_email', 'approve_url', 'portal_url'],
            self::GpwApproved, self::GpwRejected => [...$company, 'name', 'project', 'cow_code', 'period', 'decided_by', 'note', 'project_url'],
            self::ClientPasswordReset => [...$company, 'name', 'email', 'portal_name', 'portal_url', 'reset_link', 'minutes'],
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
