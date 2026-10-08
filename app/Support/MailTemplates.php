<?php

namespace App\Support;

use App\Enums\MailTemplateKey;
use App\Models\MailTemplate;
use Illuminate\Support\Str;

/**
 * The system e-mails and their texts. Administrators edit them under System > E-mail templates;
 * the defaults below are used until then (and for "reset to default").
 * Bodies are Markdown; placeholders like {client_name} are replaced when the mail is sent.
 */
class MailTemplates
{
    /**
     * @return array{subject: string, body: string}
     */
    public static function default(MailTemplateKey $key, string $locale): array
    {
        $templates = self::defaults()[$key->value];

        return $templates[$locale] ?? $templates['nl'];
    }

    /**
     * Makes sure every template exists in every language, so they can all be edited.
     */
    public static function sync(): void
    {
        foreach (MailTemplateKey::cases() as $key) {
            foreach (array_keys(config('app.locales')) as $locale) {
                MailTemplate::query()->firstOrCreate(['key' => $key->value, 'locale' => $locale], self::default($key, $locale));
            }
        }
    }

    /**
     * The subject and HTML body for a mail, in the given language.
     *
     * @param  array<string, scalar|null>  $values
     * @return array{subject: string, html: string}
     */
    public static function render(MailTemplateKey $key, string $locale, array $values): array
    {
        $template = MailTemplate::query()->where('key', $key->value)->where('locale', $locale)->where('is_active', true)->first();
        $source = $template ? ['subject' => $template->subject, 'body' => $template->body] : self::default($key, $locale);

        $values = [
            'company_name' => settings()->get('finance.company_name') ?: settings()->get('general.company_name') ?: settings()->siteName(),
            'site_name' => settings()->siteName(),
            ...$values,
        ];

        return [
            'subject' => self::fill($source['subject'], $values, escapeMarkdown: false),
            'html' => Str::markdown(self::fill($source['body'], $values, escapeMarkdown: true), ['html_input' => 'strip', 'allow_unsafe_links' => false]),
        ];
    }

    /**
     * @param  array<string, scalar|null>  $values
     */
    public static function fill(string $text, array $values, bool $escapeMarkdown): string
    {
        return (string) preg_replace_callback('/\{([a-z_]+)\}/', function (array $match) use ($values, $escapeMarkdown): string {
            if (! array_key_exists($match[1], $values)) {
                return $match[0];
            }

            $value = (string) $values[$match[1]];

            // Links stay usable inside Markdown links; other values cannot add formatting or HTML.
            return $escapeMarkdown && ! str_starts_with($value, 'http') ? addcslashes(e($value), '\\`*_[]#<>|') : $value;
        }, $text);
    }

    /**
     * @return array<string, array<string, array{subject: string, body: string}>>
     */
    public static function defaults(): array
    {
        return [
            MailTemplateKey::WelcomeEmployee->value => [
                'nl' => ['subject' => 'Welkom bij {site_name} – je login', 'body' => "Hallo {name},\n\nWelkom! Er is een account voor je aangemaakt op {site_name}.\n\nJe logt in met je e-mailadres: **{email}**\n\n[Kies je wachtwoord]({password_link})\n\nDaarna log je altijd in op {login_url} met je e-mailadres en dat wachtwoord. De link werkt {link_days} dagen en maar één keer. Verlopen? Gebruik \"Wachtwoord vergeten?\" op de inlogpagina.\n\nMet vriendelijke groeten,\n{company_name}"],
                'fr' => ['subject' => 'Bienvenue sur {site_name} – votre accès', 'body' => "Bonjour {name},\n\nBienvenue ! Un compte a été créé pour vous sur {site_name}.\n\nVous vous connectez avec votre adresse e-mail : **{email}**\n\n[Choisir votre mot de passe]({password_link})\n\nEnsuite, vous vous connectez toujours sur {login_url} avec votre adresse e-mail et ce mot de passe. Le lien fonctionne {link_days} jours et une seule fois. Expiré ? Utilisez « Mot de passe oublié ? ».\n\nCordialement,\n{company_name}"],
                'en' => ['subject' => 'Welcome to {site_name} – your login', 'body' => "Hello {name},\n\nWelcome! An account has been made for you on {site_name}.\n\nYou log in with your e-mail address: **{email}**\n\n[Choose your password]({password_link})\n\nAfter that you always log in at {login_url} with your e-mail address and that password. The link works for {link_days} days and only once. Expired? Use \"Forgot password?\" on the login page.\n\nKind regards,\n{company_name}"],
            ],
            MailTemplateKey::InvoiceSent->value => [
                'nl' => ['subject' => 'Factuur {number} van {company_name}', 'body' => "Beste {client_name},\n\nIn bijlage vindt u factuur **{number}** van {issue_date} voor een bedrag van **{amount}**.\n\nGelieve dit bedrag uiterlijk op **{due_date}** over te maken op {iban} met de gestructureerde mededeling **{structured_reference}**.\n\nMet vriendelijke groeten,\n{company_name}"],
                'fr' => ['subject' => 'Facture {number} de {company_name}', 'body' => "Madame, Monsieur,\n\nVeuillez trouver ci-joint la facture **{number}** du {issue_date} d'un montant de **{amount}**.\n\nMerci de verser ce montant au plus tard le **{due_date}** sur le compte {iban} avec la communication structurée **{structured_reference}**.\n\nCordialement,\n{company_name}"],
                'en' => ['subject' => 'Invoice {number} from {company_name}', 'body' => "Dear {client_name},\n\nPlease find attached invoice **{number}** of {issue_date} for **{amount}**.\n\nPlease pay this amount by **{due_date}** to {iban} with the structured reference **{structured_reference}**.\n\nKind regards,\n{company_name}"],
            ],
            MailTemplateKey::CreditNoteSent->value => [
                'nl' => ['subject' => 'Creditnota {number} van {company_name}', 'body' => "Beste {client_name},\n\nIn bijlage vindt u creditnota **{number}** van {issue_date} voor een bedrag van **{amount}**.\n\nMet vriendelijke groeten,\n{company_name}"],
                'fr' => ['subject' => 'Note de crédit {number} de {company_name}', 'body' => "Madame, Monsieur,\n\nVeuillez trouver ci-joint la note de crédit **{number}** du {issue_date} d'un montant de **{amount}**.\n\nCordialement,\n{company_name}"],
                'en' => ['subject' => 'Credit note {number} from {company_name}', 'body' => "Dear {client_name},\n\nPlease find attached credit note **{number}** of {issue_date} for **{amount}**.\n\nKind regards,\n{company_name}"],
            ],
            MailTemplateKey::PaymentReminder1->value => [
                'nl' => ['subject' => 'Herinnering: factuur {number}', 'body' => "Beste {client_name},\n\nVolgens onze gegevens staat factuur **{number}** van {issue_date} ({amount}) nog open. De vervaldag was {due_date}.\n\nWellicht is dit aan uw aandacht ontsnapt. Gelieve het bedrag over te maken op {iban} met mededeling **{structured_reference}**. Heeft u intussen betaald, dan mag u dit bericht als niet verzonden beschouwen.\n\nMet vriendelijke groeten,\n{company_name}"],
                'fr' => ['subject' => 'Rappel : facture {number}', 'body' => "Madame, Monsieur,\n\nSauf erreur de notre part, la facture **{number}** du {issue_date} ({amount}) n'est pas encore réglée. L'échéance était le {due_date}.\n\nMerci de verser le montant sur le compte {iban} avec la communication **{structured_reference}**. Si vous avez déjà payé, veuillez ne pas tenir compte de ce message.\n\nCordialement,\n{company_name}"],
                'en' => ['subject' => 'Reminder: invoice {number}', 'body' => "Dear {client_name},\n\nAccording to our records invoice **{number}** of {issue_date} ({amount}) is still open. It was due on {due_date}.\n\nPlease pay the amount to {iban} with reference **{structured_reference}**. If you have already paid, please disregard this message.\n\nKind regards,\n{company_name}"],
            ],
            MailTemplateKey::PaymentReminder2->value => [
                'nl' => ['subject' => 'Tweede herinnering: factuur {number}', 'body' => "Beste {client_name},\n\nOndanks onze eerdere herinnering staat factuur **{number}** ({amount}) nog steeds open, inmiddels {days_overdue} dagen na de vervaldag ({due_date}).\n\nWij verzoeken u vriendelijk het bedrag binnen 8 dagen te betalen op {iban} met mededeling **{structured_reference}**.\n\nMet vriendelijke groeten,\n{company_name}"],
                'fr' => ['subject' => 'Deuxième rappel : facture {number}', 'body' => "Madame, Monsieur,\n\nMalgré notre précédent rappel, la facture **{number}** ({amount}) reste impayée, {days_overdue} jours après l'échéance ({due_date}).\n\nNous vous prions de régler ce montant dans les 8 jours sur le compte {iban} avec la communication **{structured_reference}**.\n\nCordialement,\n{company_name}"],
                'en' => ['subject' => 'Second reminder: invoice {number}', 'body' => "Dear {client_name},\n\nDespite our earlier reminder, invoice **{number}** ({amount}) is still open, {days_overdue} days after the due date ({due_date}).\n\nPlease pay the amount within 8 days to {iban} with reference **{structured_reference}**.\n\nKind regards,\n{company_name}"],
            ],
            MailTemplateKey::PaymentReminder3->value => [
                'nl' => ['subject' => 'Laatste herinnering: factuur {number}', 'body' => "Beste {client_name},\n\nFactuur **{number}** ({amount}) is {days_overdue} dagen vervallen en werd ondanks onze herinneringen nog niet betaald.\n\nDit is onze laatste herinnering. Zonder betaling binnen 8 dagen op {iban} (mededeling **{structured_reference}**) zijn wij genoodzaakt verdere stappen te ondernemen, zoals voorzien in onze algemene voorwaarden.\n\nMet vriendelijke groeten,\n{company_name}"],
                'fr' => ['subject' => 'Dernier rappel : facture {number}', 'body' => "Madame, Monsieur,\n\nLa facture **{number}** ({amount}) est échue depuis {days_overdue} jours et reste impayée malgré nos rappels.\n\nCeci est notre dernier rappel. Sans paiement dans les 8 jours sur le compte {iban} (communication **{structured_reference}**), nous serons contraints d'entreprendre d'autres démarches, comme prévu dans nos conditions générales.\n\nCordialement,\n{company_name}"],
                'en' => ['subject' => 'Final reminder: invoice {number}', 'body' => "Dear {client_name},\n\nInvoice **{number}** ({amount}) is {days_overdue} days overdue and has not been paid despite our reminders.\n\nThis is our final reminder. Without payment within 8 days to {iban} (reference **{structured_reference}**) we will have to take further steps, as set out in our terms and conditions.\n\nKind regards,\n{company_name}"],
            ],
        ];
    }
}
