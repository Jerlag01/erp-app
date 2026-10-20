<?php

// Privacy policy of the mobile app (public page /privacy). :app = the app name, :company = the company.

return [
    'title' => 'Privacybeleid :app',
    'updated' => 'Laatst bijgewerkt op :date',
    'language' => 'Taal',
    'intro' => 'Dit privacybeleid legt uit welke persoonsgegevens de mobiele app :app en het bijhorende ERP-platform van :company verwerken, waarom, hoe lang en welke rechten je hebt. De app is uitsluitend bedoeld voor medewerkers van :company; een account wordt aangemaakt door de werkgever.',
    'sections' => [
        'controller' => [
            'title' => '1. Verwerkingsverantwoordelijke',
            'body' => 'De verwerkingsverantwoordelijke voor je gegevens is:',
            'contact' => 'Vragen over privacy of je rechten? Neem contact op via :email.',
            'contact_hr' => 'Vragen over privacy of je rechten? Neem contact op met de administratie of personeelsdienst.',
        ],
        'data' => [
            'title' => '2. Welke gegevens we verwerken',
            'items' => [
                'Accountgegevens: naam, e-mailadres, telefoonnummer, functie, ploeg en taal.',
                'Gegevens uit het personeelsregister die je in de app bekijkt, zoals adres, contactpersonen in noodgevallen, kledingmaten en rijbewijs.',
                'Prestaties en planning: uren, kilometers, projecten en werven, je planning en die van collega\'s die je mag zien.',
                'Verlof en afwezigheden: verlofaanvragen, overuren als verlof en ziektemeldingen, met het doktersattest dat je zelf oplaadt (een gezondheidsgegeven).',
                'Werkdocumenten: de jaarlijkse keuring van materiaal, batterijdossiers met metingen en foto\'s van uitgevoerde werken, en toolboxmeetings.',
                'Handtekeningen die je in de app zet, met je naam en het tijdstip.',
                'Meldingen die je doet: incidenten (met foto\'s en de plaats die je kiest) en inzendingen in de ideeën- en klachtenbus.',
                'Technische gegevens: een toestelnaam en -id, het tijdstip en IP-adres van het laatste gebruik, en het pushtoken voor meldingen.',
            ],
            'not' => 'De app gebruikt geen GPS-locatie, leest geen contacten en gebruikt de microfoon niet. Er is geen reclame en er worden geen gegevens verkocht of voor profilering gebruikt.',
        ],
        'purposes' => [
            'title' => '3. Waarom en op welke grond',
            'items' => [
                'Het uitvoeren van je arbeidsovereenkomst: planning, prestaties, verlof, werkdocumenten en communicatie.',
                'Wettelijke verplichtingen: sociale en fiscale wetgeving (prestaties, afwezigheden, ziekteattesten) en de welzijnswet (incidentmeldingen, toolboxmeetings, keuring van materiaal).',
                'Het gerechtvaardigd belang van :company: een veilige en goed werkende app, beveiliging van accounts en het opvolgen van ideeën en klachten.',
            ],
            'health' => 'Een ziekteattest wordt enkel verwerkt om aan de verplichtingen van het arbeids- en socialezekerheidsrecht te voldoen en is enkel zichtbaar voor jezelf en de administratie.',
        ],
        'permissions' => [
            'title' => '4. Toestemmingen op je toestel',
            'items' => [
                'Camera: om een foto te nemen van een attest, van uitgevoerde werken of van een incident — enkel wanneer je zelf op de knop drukt.',
                'Foto\'s en bestanden: om een bestaande foto of PDF te kiezen die je wil opladen.',
                'Meldingen: om je te verwittigen van wijzigingen in je planning, nieuwe toolboxmeetings, keuringen en beslissingen over verlof.',
            ],
            'revoke' => 'Je kan deze toestemmingen altijd intrekken in de instellingen van je toestel; de app blijft werken, maar die functie dan niet.',
        ],
        'sharing' => [
            'title' => '5. Met wie we gegevens delen',
            'body' => 'Je gegevens blijven binnen :company en worden enkel gezien door wie ze voor zijn werk nodig heeft (bijvoorbeeld je leidinggevende of de administratie). Voor het verzenden van pushmeldingen gebruiken we Expo (650 Industries, Inc.) en Google Firebase Cloud Messaging; zij ontvangen enkel het pushtoken van je toestel en de tekst van de melding. De app wordt verspreid via Google Play of via het platform zelf. Wanneer gegevens daarbij buiten de Europese Unie terechtkomen, gebeurt dat met passende waarborgen, zoals de modelcontractbepalingen van de Europese Commissie.',
        ],
        'security' => [
            'title' => '6. Bewaring en beveiliging',
            'items' => [
                'De gegevens worden bewaard op de ERP-server van :company en altijd versleuteld verzonden (HTTPS).',
                'Op je toestel bewaart de app enkel een aanmeldsleutel, in de beveiligde opslag van het toestel; op de server staat daarvan enkel een onleesbare (gehashte) versie.',
                'Gevoelige gegevens zoals het rijksregisternummer, het bankrekeningnummer en het rijbewijsnummer worden versleuteld opgeslagen.',
            ],
        ],
        'retention' => [
            'title' => '7. Hoe lang we gegevens bewaren',
            'body' => 'We bewaren gegevens zolang nodig voor je tewerkstelling en daarna zolang de wet het vereist (bijvoorbeeld de bewaartermijnen van het sociaal en fiscaal recht). Een aanmeldsleutel van de app is een jaar geldig en wordt ingetrokken zodra je afmeldt of de toegang wordt stopgezet; het pushtoken wordt dan niet meer gebruikt en verdwijnt als de app wordt verwijderd.',
        ],
        'rights' => [
            'title' => '8. Je rechten',
            'body' => 'Je hebt het recht op inzage, verbetering, verwijdering en beperking van je gegevens, het recht om bezwaar te maken en het recht op overdraagbaarheid, binnen de grenzen van de wet. Een aanvraag richt je aan de contactgegevens hierboven. Ben je niet tevreden over hoe we met je gegevens omgaan, dan kan je een klacht indienen bij de Gegevensbeschermingsautoriteit, Drukpersstraat 35, 1000 Brussel (www.gegevensbeschermingsautoriteit.be).',
            'delete' => 'Je account in de app wordt beheerd door je werkgever: wil je dat je account en gegevens verwijderd worden, vraag het dan aan de administratie.',
        ],
        'changes' => [
            'title' => '9. Wijzigingen',
            'body' => 'We kunnen dit privacybeleid aanpassen, bijvoorbeeld als de app nieuwe functies krijgt. De datum bovenaan toont wanneer het laatst gewijzigd werd.',
        ],
    ],
];
