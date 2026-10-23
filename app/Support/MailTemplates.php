<?php

namespace App\Support;

use App\Enums\MailTemplateKey;
use App\Models\MailTemplate;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * The system e-mails and their texts. Administrators edit them under System > E-mail templates;
 * the defaults below are used until then (and for "reset to default").
 * Bodies are Markdown; placeholders like {client_name} are replaced when the mail is sent.
 * Values are escaped, except an HtmlString: Markdown the caller built (and escaped) itself, such as a list.
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
     * @param  array<string, scalar|HtmlString|null>  $values
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
     * @param  array<string, scalar|HtmlString|null>  $values
     */
    public static function fill(string $text, array $values, bool $escapeMarkdown): string
    {
        return (string) preg_replace_callback('/\{([a-z_]+)\}/', function (array $match) use ($values, $escapeMarkdown): string {
            if (! array_key_exists($match[1], $values)) {
                return $match[0];
            }

            if ($values[$match[1]] instanceof HtmlString) {
                return $escapeMarkdown ? $values[$match[1]]->toHtml() : strip_tags($values[$match[1]]->toHtml());
            }

            $value = (string) $values[$match[1]];

            // Links stay usable inside Markdown links; other values cannot add formatting or HTML.
            return $escapeMarkdown && ! str_starts_with($value, 'http') ? addcslashes(e($value), '\\`*_[]#<>|') : $value;
        }, $text);
    }

    /**
     * Escapes text for use inside Markdown built by the caller (see HtmlString values).
     */
    public static function escape(string $text): string
    {
        return addcslashes(e($text), '\\`*_[]#<>|');
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
            MailTemplateKey::TimesheetWeek->value => [
                'nl' => ['subject' => 'Prestatiefiches week {week} ({from} – {until})', 'body' => "Goedendag,\n\nIn bijlage de prestatiefiches van week {week} ({from} tot {until}) van {count} werknemer(s):\n\n{summary}\n\nMet vriendelijke groeten,\n{company_name}"],
                'fr' => ['subject' => 'Fiches de prestations semaine {week} ({from} – {until})', 'body' => "Bonjour,\n\nVeuillez trouver ci-joint les fiches de prestations de la semaine {week} ({from} au {until}) de {count} employé(s) :\n\n{summary}\n\nCordialement,\n{company_name}"],
                'en' => ['subject' => 'Timesheets week {week} ({from} – {until})', 'body' => "Hello,\n\nAttached are the timesheets of week {week} ({from} to {until}) of {count} employee(s):\n\n{summary}\n\nKind regards,\n{company_name}"],
            ],
            MailTemplateKey::TimesheetEmployee->value => [
                'nl' => ['subject' => 'Je prestatiefiche van week {week} ({from} – {until})', 'body' => "Hallo {name},\n\nIn bijlage je prestatiefiche van week {week} ({from} tot {until}):\n\n- Gepresteerd: {hours} u\n- Meeruren deze week: {overtime} u (saldo {balance} u)\n- Kilometers: {km}\n\nKlopt er iets niet? Laat het de administratie weten.\n\nMet vriendelijke groeten,\n{company_name}"],
                'fr' => ['subject' => 'Votre fiche de prestations semaine {week} ({from} – {until})', 'body' => "Bonjour {name},\n\nVeuillez trouver ci-joint votre fiche de prestations de la semaine {week} ({from} au {until}) :\n\n- Presté : {hours} h\n- Heures supplémentaires cette semaine : {overtime} h (solde {balance} h)\n- Kilomètres : {km}\n\nQuelque chose ne va pas ? Prévenez l’administration.\n\nCordialement,\n{company_name}"],
                'en' => ['subject' => 'Your timesheet of week {week} ({from} – {until})', 'body' => "Hello {name},\n\nAttached is your timesheet of week {week} ({from} to {until}):\n\n- Worked: {hours} h\n- Overtime this week: {overtime} h (balance {balance} h)\n- Kilometres: {km}\n\nSomething not right? Let the administration know.\n\nKind regards,\n{company_name}"],
            ],
            MailTemplateKey::ClientWelcome->value => [
                'nl' => ['subject' => 'Uw toegang tot {portal_name}', 'body' => "Beste {name},\n\n{company_name} heeft voor u een account aangemaakt op **{portal_name}**: een overzicht van de projecten die wij voor {company} uitvoeren.\n\nU kunt er het volgende raadplegen:\n\n{overviews}\n\nU logt in met uw e-mailadres: **{email}**\n\n[Kies uw wachtwoord]({password_link})\n\nDaarna logt u altijd in op {portal_url} met uw e-mailadres en dat wachtwoord. De link werkt {link_days} dagen en maar één keer. Verlopen? Gebruik \"Wachtwoord vergeten?\" op de inlogpagina.\n\nMet vriendelijke groeten,\n{company_name}"],
                'fr' => ['subject' => 'Votre accès à {portal_name}', 'body' => "Bonjour {name},\n\n{company_name} a créé pour vous un compte sur **{portal_name}** : un aperçu des projets que nous réalisons pour {company}.\n\nVous pouvez y consulter :\n\n{overviews}\n\nVous vous connectez avec votre adresse e-mail : **{email}**\n\n[Choisir votre mot de passe]({password_link})\n\nEnsuite, vous vous connectez toujours sur {portal_url} avec votre adresse e-mail et ce mot de passe. Le lien fonctionne {link_days} jours et une seule fois. Expiré ? Utilisez « Mot de passe oublié ? » sur la page de connexion.\n\nCordialement,\n{company_name}"],
                'en' => ['subject' => 'Your access to {portal_name}', 'body' => "Dear {name},\n\n{company_name} has made an account for you on **{portal_name}**: an overview of the projects we carry out for {company}.\n\nYou can consult:\n\n{overviews}\n\nYou log in with your e-mail address: **{email}**\n\n[Choose your password]({password_link})\n\nAfter that you always log in at {portal_url} with your e-mail address and that password. The link works for {link_days} days and only once. Expired? Use \"Forgot password?\" on the login page.\n\nKind regards,\n{company_name}"],
            ],
            MailTemplateKey::ClientPasswordReset->value => [
                'nl' => ['subject' => 'Nieuw wachtwoord voor {portal_name}', 'body' => "Beste {name},\n\nEr is gevraagd om het wachtwoord van uw account ({email}) op {portal_name} opnieuw in te stellen.\n\n[Kies een nieuw wachtwoord]({reset_link})\n\nDeze link werkt {minutes} minuten. Heeft u dit niet gevraagd? Dan hoeft u niets te doen: uw wachtwoord blijft ongewijzigd.\n\nMet vriendelijke groeten,\n{company_name}"],
                'fr' => ['subject' => 'Nouveau mot de passe pour {portal_name}', 'body' => "Bonjour {name},\n\nUne réinitialisation du mot de passe de votre compte ({email}) sur {portal_name} a été demandée.\n\n[Choisir un nouveau mot de passe]({reset_link})\n\nCe lien fonctionne {minutes} minutes. Vous n'avez rien demandé ? Vous ne devez rien faire : votre mot de passe reste inchangé.\n\nCordialement,\n{company_name}"],
                'en' => ['subject' => 'New password for {portal_name}', 'body' => "Dear {name},\n\nA password reset was requested for your account ({email}) on {portal_name}.\n\n[Choose a new password]({reset_link})\n\nThis link works for {minutes} minutes. Didn't ask for this? Then you don't need to do anything: your password stays the same.\n\nKind regards,\n{company_name}"],
            ],
            MailTemplateKey::GpwRequest->value => [
                'nl' => ['subject' => 'GPW-aanvraag {project} ({cow_code}): {period}', 'body' => "Beste {contact_name},\n\nVoor project **{project}** op COW-site **{cow_code}** ({address}) vragen wij een GPW aan voor de volgende periode:\n\n**{period}**\n\n{description}\n\nOpmerkingen: {remarks}\n\n[GPW bekijken en goedkeuren]({approve_url})\n\nU kunt de aanvraag ook goedkeuren in de Global overview for Client: {portal_url}\n\nAangevraagd door {requested_by} ({requester_email}).\n\nMet vriendelijke groeten,\n{company_name}"],
                'fr' => ['subject' => 'Demande de GPW {project} ({cow_code}) : {period}', 'body' => "Bonjour {contact_name},\n\nPour le projet **{project}** sur le site COW **{cow_code}** ({address}), nous demandons un GPW pour la période suivante :\n\n**{period}**\n\n{description}\n\nRemarques : {remarks}\n\n[Voir et approuver le GPW]({approve_url})\n\nVous pouvez aussi approuver la demande dans le Global overview for Client : {portal_url}\n\nDemandé par {requested_by} ({requester_email}).\n\nCordialement,\n{company_name}"],
                'en' => ['subject' => 'GPW request {project} ({cow_code}): {period}', 'body' => "Dear {contact_name},\n\nFor project **{project}** at COW site **{cow_code}** ({address}) we request a GPW for the following period:\n\n**{period}**\n\n{description}\n\nRemarks: {remarks}\n\n[View and approve the GPW]({approve_url})\n\nYou can also approve the request in the Global overview for Client: {portal_url}\n\nRequested by {requested_by} ({requester_email}).\n\nKind regards,\n{company_name}"],
            ],
            MailTemplateKey::GpwApproved->value => [
                'nl' => ['subject' => 'GPW goedgekeurd: {project} ({period})', 'body' => "Hallo {name},\n\n{decided_by} heeft de GPW voor project **{project}** ({cow_code}) goedgekeurd voor **{period}**.\n\nOpmerking van de klant: {note}\n\nJe kan het project nu in die periode inplannen voor een ploeg.\n\n[Open het project]({project_url})\n\nMet vriendelijke groeten,\n{company_name}"],
                'fr' => ['subject' => 'GPW approuvé : {project} ({period})', 'body' => "Bonjour {name},\n\n{decided_by} a approuvé le GPW du projet **{project}** ({cow_code}) pour **{period}**.\n\nRemarque du client : {note}\n\nVous pouvez maintenant planifier une équipe pour le projet dans cette période.\n\n[Ouvrir le projet]({project_url})\n\nCordialement,\n{company_name}"],
                'en' => ['subject' => 'GPW approved: {project} ({period})', 'body' => "Hello {name},\n\n{decided_by} approved the GPW for project **{project}** ({cow_code}) for **{period}**.\n\nNote from the client: {note}\n\nYou can now plan a team for the project in that period.\n\n[Open the project]({project_url})\n\nKind regards,\n{company_name}"],
            ],
            MailTemplateKey::GpwRejected->value => [
                'nl' => ['subject' => 'GPW geweigerd: {project} ({period})', 'body' => "Hallo {name},\n\n{decided_by} heeft de GPW voor project **{project}** ({cow_code}) voor **{period}** geweigerd.\n\nReden: {note}\n\nVraag eventueel een nieuwe periode aan.\n\n[Open het project]({project_url})\n\nMet vriendelijke groeten,\n{company_name}"],
                'fr' => ['subject' => 'GPW refusé : {project} ({period})', 'body' => "Bonjour {name},\n\n{decided_by} a refusé le GPW du projet **{project}** ({cow_code}) pour **{period}**.\n\nRaison : {note}\n\nDemandez éventuellement une nouvelle période.\n\n[Ouvrir le projet]({project_url})\n\nCordialement,\n{company_name}"],
                'en' => ['subject' => 'GPW refused: {project} ({period})', 'body' => "Hello {name},\n\n{decided_by} refused the GPW for project **{project}** ({cow_code}) for **{period}**.\n\nReason: {note}\n\nYou may request a new period.\n\n[Open the project]({project_url})\n\nKind regards,\n{company_name}"],
            ],
            MailTemplateKey::SuggestionResponse->value => [
                'nl' => ['subject' => 'Nieuwe reactie in de ideeënbus: {type} van {submitted_on}', 'body' => "Hallo {name},\n\n{author} heeft gereageerd op het/de {type} van {submitted_on}:\n\n{message}\n\n[Bekijk het gesprek en reageer]({url})\n\nMet vriendelijke groeten,\n{company_name}"],
                'fr' => ['subject' => 'Nouvelle réponse dans la boîte à idées : {type} du {submitted_on}', 'body' => "Bonjour {name},\n\n{author} a répondu à : {type} du {submitted_on} :\n\n{message}\n\n[Voir la conversation et répondre]({url})\n\nCordialement,\n{company_name}"],
                'en' => ['subject' => 'New reply in the idea box: {type} of {submitted_on}', 'body' => "Hello {name},\n\n{author} replied to the {type} of {submitted_on}:\n\n{message}\n\n[View the conversation and reply]({url})\n\nKind regards,\n{company_name}"],
            ],
            MailTemplateKey::PlanningCreated->value => [
                'nl' => ['subject' => 'Nieuw in je planning', 'body' => "Hallo {name},\n\n{changed_by} heeft het volgende in je planning gezet:\n\n{items}\n\n[Bekijk je planning]({planning_url})\n\nMet vriendelijke groeten,\n{company_name}"],
                'fr' => ['subject' => 'Nouveau dans votre planning', 'body' => "Bonjour {name},\n\n{changed_by} a ajouté ceci à votre planning :\n\n{items}\n\n[Voir votre planning]({planning_url})\n\nCordialement,\n{company_name}"],
                'en' => ['subject' => 'New in your planning', 'body' => "Hello {name},\n\n{changed_by} added the following to your planning:\n\n{items}\n\n[View your planning]({planning_url})\n\nKind regards,\n{company_name}"],
            ],
            MailTemplateKey::PlanningUpdated->value => [
                'nl' => ['subject' => 'Wijziging in je planning', 'body' => "Hallo {name},\n\n{changed_by} heeft je planning gewijzigd. Nieuw:\n\n{items}\n\nVoorheen:\n\n{previous}\n\n[Bekijk je planning]({planning_url})\n\nMet vriendelijke groeten,\n{company_name}"],
                'fr' => ['subject' => 'Modification de votre planning', 'body' => "Bonjour {name},\n\n{changed_by} a modifié votre planning. Nouveau :\n\n{items}\n\nAuparavant :\n\n{previous}\n\n[Voir votre planning]({planning_url})\n\nCordialement,\n{company_name}"],
                'en' => ['subject' => 'Change in your planning', 'body' => "Hello {name},\n\n{changed_by} changed your planning. New:\n\n{items}\n\nBefore:\n\n{previous}\n\n[View your planning]({planning_url})\n\nKind regards,\n{company_name}"],
            ],
            MailTemplateKey::PlanningDeleted->value => [
                'nl' => ['subject' => 'Verwijderd uit je planning', 'body' => "Hallo {name},\n\n{changed_by} heeft het volgende uit je planning gehaald:\n\n{items}\n\n[Bekijk je planning]({planning_url})\n\nMet vriendelijke groeten,\n{company_name}"],
                'fr' => ['subject' => 'Retiré de votre planning', 'body' => "Bonjour {name},\n\n{changed_by} a retiré ceci de votre planning :\n\n{items}\n\n[Voir votre planning]({planning_url})\n\nCordialement,\n{company_name}"],
                'en' => ['subject' => 'Removed from your planning', 'body' => "Hello {name},\n\n{changed_by} removed the following from your planning:\n\n{items}\n\n[View your planning]({planning_url})\n\nKind regards,\n{company_name}"],
            ],
        ];
    }
}
