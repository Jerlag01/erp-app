<?php

// GDPR / privacy statement of the ERP platform (public page /gdpr). :company = the company, :site = the platform's name.

return [
    'title' => 'Privacyverklaring (GDPR) :site',
    'updated' => 'Laatst bijgewerkt op :date',
    'language' => 'Taal',
    'menu' => 'Privacy (GDPR)',
    'intro' => 'Deze verklaring legt uit welke persoonsgegevens :company verwerkt in haar ERP-platform :site, waarom, hoe lang, met wie ze gedeeld worden en welke rechten je hebt volgens de Algemene Verordening Gegevensbescherming (AVG/GDPR). Ze geldt voor iedereen van wie gegevens in het platform staan: medewerkers, gebruikers van het klantenportaal, contactpersonen van klanten, werflocaties en leveranciers, en particuliere klanten.',
    'app_policy' => 'Voor de mobiele app geldt daarnaast het privacybeleid van de app:',
    'sections' => [
        'controller' => [
            'title' => '1. Verwerkingsverantwoordelijke en contact',
            'body' => 'De verwerkingsverantwoordelijke is:',
            'contact' => 'Voor alle vragen over privacy en om je rechten uit te oefenen, kan je terecht bij:',
        ],
        'subjects' => [
            'title' => '2. Van wie we gegevens verwerken',
            'items' => [
                'Medewerkers (ook uitzendkrachten en stagiairs) die een account hebben of in het personeelsregister staan.',
                'Gebruikers van het klantenportaal, aangemaakt op vraag van onze klanten.',
                'Contactpersonen van klanten, werflocaties (COW-sites) en leveranciers.',
                'Particuliere klanten van wie we een project uitvoeren.',
            ],
        ],
        'data' => [
            'title' => '3. Welke gegevens we verwerken',
            'items' => [
                'Identiteit en contact: naam, e-mailadres, telefoonnummer, adres, functie, ploeg, taal en bedrijf.',
                'Personeelsregister: geboortedatum, rijksregisternummer, bankrekeningnummer, rijbewijs, contactpersonen in noodgevallen, kledingmaten, contract en categorie, certificaten en opleidingen.',
                'Werk: planning, prestaties (uren, kilometers, projecten en werven), verlof en afwezigheden, het gebruik van voertuigen, materieel en IT-middelen.',
                'Gezondheid: het ziekteattest dat bij een afwezigheid wordt opgeladen en, waar nodig, informatie in een incidentmelding.',
                'Werkdocumenten: projectbestanden, foto\'s van uitgevoerde werken, batterijdossiers, as-builts, toolboxmeetings en handtekeningen met naam en tijdstip.',
                'Meldingen en communicatie: incidenten, ideeën en klachten met hun antwoorden, GPW-aanvragen en de e-mails die het platform verstuurt.',
                'Klantenportaal: account, rechten, aanmeldingen en de beslissingen die de gebruiker neemt (bv. het goedkeuren van een GPW).',
                'Facturatie: klantgegevens, facturen, betalingen en inkomende facturen.',
                'Technische gegevens: aanmeldingen, tijdstip en IP-adres van het laatste gebruik, de taal en een sessiecookie.',
            ],
            'not' => 'We gebruiken geen gegevens voor reclame of profilering en verkopen geen gegevens.',
        ],
        'purposes' => [
            'title' => '4. Waarom en op welke rechtsgrond',
            'items' => [
                'Uitvoering van de arbeidsovereenkomst: planning, prestaties, verlof, loon- en personeelsadministratie, werkmiddelen en communicatie.',
                'Wettelijke verplichtingen: sociale en fiscale wetgeving, de welzijnswet (incidenten, opleidingen, keuringen, toolboxmeetings), de afvalwetgeving en de boekhoudwetgeving.',
                'Uitvoering van overeenkomsten met klanten en leveranciers: projecten, levering, opvolging, het klantenportaal en facturatie.',
                'Gerechtvaardigd belang: een veilig en goed werkend platform, beveiliging van accounts, kwaliteitsopvolging en het behandelen van ideeën en klachten.',
            ],
            'health' => 'Gezondheidsgegevens (ziekteattesten) verwerken we enkel om te voldoen aan het arbeids- en socialezekerheidsrecht. Ze zijn enkel zichtbaar voor de betrokken medewerker en de administratie.',
        ],
        'sharing' => [
            'title' => '5. Met wie we gegevens delen',
            'body' => 'Binnen :company ziet enkel wie de gegevens voor zijn werk nodig heeft ze (rechten per rol en per ploeg). Gebruikers van het klantenportaal zien enkel de projecten waartoe ze toegang kregen, nooit personeelsgegevens of prijzen. Waar nodig delen we gegevens met:',
            'items' => [
                'onze klanten (bv. Proximus), voor zover nodig voor de uitvoering van hun projecten, zoals namen op werkdocumenten en handtekeningen;',
                'dienstverleners die voor ons werken (verwerkers): de hosting en het e-mailverkeer van het platform, Microsoft 365 wanneer de planning met Outlook gesynchroniseerd wordt, Expo en Google Firebase voor pushmeldingen van de app, en een Peppol-dienstverlener voor elektronische facturen;',
                'overheden en instanties wanneer de wet dat vereist (bv. sociaal secretariaat, keuringsorganismen, afvalverwerkers).',
            ],
            'transfers' => 'Wanneer gegevens daarbij buiten de Europese Economische Ruimte terechtkomen, gebeurt dat enkel met passende waarborgen, zoals de modelcontractbepalingen van de Europese Commissie.',
        ],
        'security' => [
            'title' => '6. Beveiliging',
            'items' => [
                'Toegang enkel met een persoonlijk account en wachtwoord; rechten per rol, ploeg en module.',
                'Alle verkeer is versleuteld (HTTPS); gevoelige gegevens zoals rijksregisternummer, bankrekeningnummer, rijbewijsnummer en wachtwoorden van installaties worden versleuteld opgeslagen.',
                'Bestanden (attesten, documenten, foto\'s) staan niet publiek: ze zijn enkel via het platform te openen door wie er recht op heeft.',
            ],
        ],
        'retention' => [
            'title' => '7. Hoe lang we gegevens bewaren',
            'body' => 'We bewaren gegevens zolang ze nodig zijn voor het doel waarvoor ze verzameld werden en daarna zolang de wet het vereist, bijvoorbeeld de bewaartermijnen van het sociaal recht (personeels- en loongegevens), het fiscaal en boekhoudkundig recht (facturen) en de welzijnswet. Projectdossiers bewaren we zolang ze voor garantie en opvolging nodig zijn. Een account van het klantenportaal kan de gebruiker zelf verwijderen; accounts van medewerkers worden afgesloten bij het einde van de tewerkstelling.',
        ],
        'cookies' => [
            'title' => '8. Cookies',
            'body' => 'Het platform gebruikt enkel functionele cookies: een sessiecookie om aangemeld te blijven en de beveiliging tegen vervalste formulieren, en je taalkeuze. Er zijn geen cookies van derden, geen statistieken en geen reclame.',
        ],
        'rights' => [
            'title' => '9. Je rechten',
            'body' => 'Je hebt het recht op inzage, verbetering, verwijdering en beperking van je gegevens, het recht om bezwaar te maken en het recht op overdraagbaarheid, binnen de grenzen van de wet. Een aanvraag stuur je naar de contactadressen hierboven; we antwoorden binnen een maand. Ben je niet tevreden over hoe we met je gegevens omgaan, dan kan je een klacht indienen bij de Gegevensbeschermingsautoriteit, Drukpersstraat 35, 1000 Brussel (www.gegevensbeschermingsautoriteit.be).',
        ],
        'changes' => [
            'title' => '10. Wijzigingen',
            'body' => 'We passen deze verklaring aan wanneer het platform of de wetgeving verandert. De datum bovenaan toont wanneer ze het laatst gewijzigd werd.',
        ],
    ],
];
