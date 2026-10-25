<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrivacyPolicyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_privacy_policy_is_public_in_three_languages(): void
    {
        $this->get('/privacy?lang=nl')
            ->assertOk()
            ->assertSee('Privacybeleid PI ERP')
            ->assertSee('Verwerkingsverantwoordelijke')
            ->assertSee('Gegevensbeschermingsautoriteit')
            ->assertSee('Drukpersstraat 4')
            ->assertSee('Neem contact op met de administratie');

        $this->get('/privacy?lang=fr')->assertOk()->assertSee('Politique de confidentialité PI ERP')->assertSee('Autorité de protection des données');
        $this->get('/privacy?lang=en')->assertOk()->assertSee('PI ERP privacy policy')->assertSee('Data controller');

        // Without ?lang= the browser's language decides.
        $this->withHeader('Accept-Language', 'fr-BE,fr;q=0.9')->get('/privacy')->assertSee('Politique de confidentialité');
        $this->withHeader('Accept-Language', 'de-DE')->get('/privacy')->assertSee('Privacybeleid');
    }

    #[Test]
    public function the_controller_and_contact_come_from_the_settings(): void
    {
        settings()->save('mobile_app', [...settings('mobile_app'), 'name' => 'PI Mobiel', 'privacy_email' => 'privacy@power-installation.be', 'privacy_company' => "Power Installation NV\nVoorbeeldstraat 1\n1000 Brussel"]);

        $this->get('/privacy?lang=nl')
            ->assertOk()
            ->assertSee('Privacybeleid PI Mobiel')
            ->assertSee('Voorbeeldstraat 1')
            ->assertSee('mailto:privacy@power-installation.be', false)
            ->assertDontSee('Drukpersstraat 4');
    }

    #[Test]
    public function the_gdpr_statement_of_the_platform_is_public_with_its_contact_addresses(): void
    {
        $this->get('/gdpr?lang=nl')
            ->assertOk()
            ->assertSee('Privacyverklaring (GDPR)')
            ->assertSee('mailto:j.lagaet@power-installation.be', false)
            ->assertSee('mailto:info@power-installation.be', false)
            ->assertSee('Gegevensbeschermingsautoriteit')
            ->assertSee(route('privacy'));
        $this->get('/gdpr?lang=fr')->assertOk()->assertSee('Déclaration de confidentialité (RGPD)');
        $this->get('/gdpr?lang=en')->assertOk()->assertSee('Privacy statement (GDPR)');
        $this->get('/privacy?lang=nl')->assertSee(route('gdpr'));

        // The addresses come from Settings > General.
        settings()->save('general', [...settings('general'), 'gdpr_emails' => ['privacy@example.com']]);
        $this->get('/gdpr')->assertSee('privacy@example.com')->assertDontSee('info@power-installation.be');

        // Linked under every sign-in form.
        $this->get('/login')->assertSee('data-gdpr-link', false);
        $this->get('/client/login')->assertSee('data-gdpr-link', false);
    }
}
