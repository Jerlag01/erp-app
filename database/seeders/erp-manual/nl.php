<?php

/*
 * ERP-app handleiding, Nederlands. Elke sleutel is één artikel (zie ErpManualSeeder); de volgorde
 * hier is de volgorde in de kennisbank.
 */

return [
    'category' => [
        'name' => 'ERP-app handleiding',
        'description' => 'Hoe het platform en elke module werken: van aanmelden tot facturen, voor werknemers en beheerders.',
    ],

    'articles' => [
        'getting-started' => [
            'title' => 'Aan de slag: aanmelden, menu en taal',
            'body' => <<<'MD'
## Waarvoor dient het platform?

Het ERP-platform bundelt alles van het bedrijf op één plaats: projecten, planning, prestaties, verlof, materieel, magazijn, veiligheid, financieel en meer. Elk onderdeel is een **module**; welke modules aan staan, bepaalt de beheerder.

## Aanmelden

- Je krijgt een **welkomstmail** met een link om zelf je wachtwoord te kiezen. De link werkt 7 dagen en maar één keer.
- Daarna meld je je altijd aan met je **e-mailadres en wachtwoord**.
- Wachtwoord vergeten? Klik op de aanmeldpagina op **"Wachtwoord vergeten?"**: je krijgt een mail met een nieuwe link.

## Twee kanten: werknemers en beheer

- **Werknemerskant** (het adres zonder /admin): je eigen planning, prestatiefiche, verlof, toolboxmeetings, incident melden, ideeënbus, kennisbank en de modules waarvoor je rechten hebt.
- **Beheerkant** (/admin): enkel voor beheerders. Daar staan ook de instellingen, rollen, gebruikers, e-mailsjablonen en het klantenportaal.
- Beheerders wisselen tussen beide via het **gebruikersmenu** rechtsboven.

## Het scherm

- **Linkermenu**: per groep (Personeel, Projecten, Planning, Veiligheid, …). Je ziet enkel wat je mag gebruiken.
- **Bovenbalk**: zoeken, de **ideeënbus**, eventueel een **mededeling** van de directie, het **belletje** met je meldingen en je **gebruikersmenu**.
- **Dashboard**: tegels en lijsten die voor jou van belang zijn (bv. keuringen die vervallen, lage voorraad, open taken).

## Taal en profiel

- Kies **Nederlands, Frans of Engels** met de taalkeuze naast je gebruikersmenu. Je keuze geldt ook voor de mails die je krijgt en voor de mobiele app.
- Via het gebruikersmenu → **Profiel** wijzig je je naam, e-mailadres of wachtwoord.

## Meldingen

Het belletje rechtsboven toont meldingen van het platform: een wijziging in je planning, een goedgekeurde verlofaanvraag, een nieuwe toolboxmeeting, … Klik op een melding om meteen naar de juiste pagina te gaan.

## Op je telefoon

Gebruik op je telefoon liefst de **mobiele app PI ERP** (zie het artikel "Mobiele app"). Op een Android-telefoon stelt het platform na het aanmelden voor om de app te downloaden.
MD,
        ],

        'roles-users' => [
            'title' => 'Gebruikers, rollen, rechten en ploegen',
            'body' => <<<'MD'
## Waarvoor?

Wat iemand ziet en mag doen, hangt af van zijn **rol** en zijn **ploeg**.

## Waar?

- **Beheer → Organisatie → Gebruikers**: de accounts.
- **Beheer → Systeem → Rollen**: de rollen en hun rechten.
- **Organisatie → Ploegen**: de ploegen en hun leden.

## Rollen en rechten

- Een rol is een verzameling **rechten per onderdeel**, bv. *Projecten: bekijken, aanmaken, bewerken* of *Verlof: goedkeuren*.
- Een **beheerdersrol** heeft automatisch alle rechten (op enkele vertrouwelijke rechten na, zoals de wachtwoorden van 4G-routers, die je expliciet aanvinkt).
- Op een gebruiker kan je de rechten van zijn rol nog **per persoon aanpassen**.
- Rechten van een module die uit staat, hebben geen effect.

## Ploegen

- Medewerkers worden ingedeeld in **ploegen** met een ploegleider.
- Een medewerker **zonder ploeg** is een *gast*: hij heeft geen rechten, wat zijn rol ook is.
- Ploegen bepalen ook wat je ziet: projecten zie je als je projectleider bent of als je ploeg aan het project is toegewezen; de planning van je ploeggenoten zie je ook.

## Een account aanmaken

- Via **Personeel → Personeelsregister** (een medewerker toevoegen maakt ook zijn login) of via **Organisatie → Gebruikers**.
- Laat het wachtwoord leeg en laat **"Welkomstmail sturen"** aan staan: de medewerker kiest dan zelf zijn wachtwoord.
- Met **"Welkomstmail sturen"** in de lijst stuur je de link opnieuw.

## Tips

- Geef rechten liefst via rollen, niet per persoon: dat blijft overzichtelijk.
- Iemand uit dienst? Zet zijn account op **inactief**; zijn gegevens blijven bewaard.
MD,
        ],

        'settings' => [
            'title' => 'Instellingen, modules en e-mailsjablonen',
            'body' => <<<'MD'
## Waar?

**Beheer → Systeem → Instellingen** en **Beheer → Systeem → E-mailsjablonen**. Alleen voor beheerders.

## Instellingen

De instellingen zijn verdeeld over tabbladen:

- **Algemeen**: naam van het platform, bedrijfsnaam, herinneringen (hoeveel dagen op voorhand je verwittigd wordt van vervaldata).
- **Modules**: zet elke module aan of uit. Een module die uit staat, verdwijnt uit het menu voor iedereen; de gegevens blijven bewaard.
- **E-mail**: de mailserver waarmee het platform mails verstuurt. Met **"Testmail versturen"** controleer je de instellingen.
- **Uiterlijk**: logo, favicon en kleur.
- **Mobiele app**: de APK uploaden, de melding op telefoons en de privacyverklaring.
- En per module waar nodig: **Financieel**, **Prestaties**, **Batterijen**, **Planning**, …

## E-mailsjablonen

- Elke e-mail die het platform verstuurt (welkomstmail, factuur, herinnering, planning, prestatiefiche, GPW-aanvraag, …) heeft een **sjabloon per taal** (NL, FR, EN).
- Pas onderwerp en tekst aan; de **plaatshouders** tussen accolades, bv. `{name}` of `{project}`, worden bij het versturen ingevuld. De lijst staat bij elk sjabloon.
- Met **Voorbeeld** zie je de mail zoals hij verstuurd wordt; met **Standaardtekst** zet je de oorspronkelijke tekst terug.
- De mail gaat altijd in de **taal van de ontvanger**.

## Tips

- Zet een module pas aan als de stamgegevens klaar zijn (bv. categorieën, locaties).
- Na het wijzigen van het logo of de kleur herlaadt de pagina vanzelf.
MD,
        ],

        'announcements' => [
            'title' => 'Mededelingen',
            'body' => <<<'MD'
## Waarvoor?

Een korte boodschap voor **alle werknemers**, bovenaan in de bovenbalk links van de ideeënbus. Bijvoorbeeld: "Vrijdag is het kantoor gesloten".

## Waar?

**Organisatie → Mededeling** (aan de werknemerskant en aan de beheerkant).

## Zo werkt het

1. Zet **"Mededeling tonen"** aan.
2. Kies een **kleur**: blauw (info), groen, oranje (let op) of rood (dringend).
3. Vul het bericht in, maximaal 200 tekens, in het **Nederlands** (verplicht) en eventueel in het **Frans** en **Engels**. Zonder vertaling zien Franstalige en Engelstalige werknemers de Nederlandse tekst.
4. Klik op **Opslaan**.

Wie de mededeling mag aanpassen, ziet in de balk een **potloodje** om ze meteen te bewerken. Onderaan de pagina staat wie ze het laatst wijzigde.

## Rechten

Beheerders, en werknemers met het recht **Mededelingen → Bewerken**. De module **Mededelingen** moet aan staan.
MD,
        ],

        'locations' => [
            'title' => 'Locaties',
            'body' => <<<'MD'
## Waarvoor?

De **vestigingen** van het bedrijf (bv. hoofdzetel, magazijn) met hun adres. Ploegen, voertuigen, materieel en voorraad worden aan een locatie gekoppeld.

## Waar?

**Organisatie → Locaties**.

## Zo werkt het

- Maak een locatie aan met naam en adres.
- Op de detailpagina zie je welke **ploegen, voertuigen, materieel en voorraad** bij die locatie horen.
- De voorraad in het magazijn wordt **per locatie** bijgehouden; zonder locatie is er dus geen voorraad.

## Rechten

Bekijken, aanmaken, bewerken en verwijderen van locaties zijn aparte rechten.
MD,
        ],

        'teams' => [
            'title' => 'Ploegen',
            'body' => <<<'MD'
## Waarvoor?

Medewerkers indelen in **ploegen** met een **ploegleider**, gekoppeld aan een locatie.

## Waar?

**Organisatie → Ploegen**.

## Zo werkt het

- Maak een ploeg aan, kies een kleur, een ploegleider en een locatie, en voeg de leden toe.
- Ploegen worden gebruikt in heel het platform:
  - **Projecten**: een ploeg toewijzen aan een project geeft de leden toegang tot dat project.
  - **Planning**: je ziet de planning van je ploeggenoten; een GPW-periode wordt ingepland per ploeg.
  - **Materieel**: materieel en voertuigen per ploeg, en de jaarlijkse controle per ploeg.
  - **Magazijn**: de voorraad van de locatie van je ploeg.

## Belangrijk

Een medewerker zonder ploeg is een **gast** en heeft geen rechten. Voeg nieuwe medewerkers dus altijd toe aan een ploeg.
MD,
        ],

        'employees' => [
            'title' => 'Personeelsregister en mijn gegevens',
            'body' => <<<'MD'
## Waarvoor?

Alle personeelsgegevens op één plaats: persoonsgegevens, tewerkstelling, noodcontacten, kledingmaten, rijbewijs, certificaten en het medisch onderzoek.

## Waar?

- **Personeel → Personeelsregister**: voor de administratie.
- **Personeel → Mijn gegevens**: elke werknemer ziet hier zijn eigen gegevens.
- **Personeel → Opleidingen & medisch onderzoek**: het overzicht van wie welke opleiding heeft en wanneer iets vervalt.

## Een medewerker toevoegen

1. Klik op **Nieuw** in het personeelsregister.
2. Vul de tabbladen in: persoonsgegevens, tewerkstelling, noodcontacten, kledingmaten, rijbewijs.
3. Bij **Login-account** wordt de login aangemaakt en vertrekt de **welkomstmail** met een link om het wachtwoord te kiezen.

## Rijbewijs

Kies één of meer **categorieën** (B, BE, C, …), het nummer en de vervaldatum, en upload een kopie (foto of PDF van voor- en achterkant).

## Certificaten en medisch onderzoek

- Certificaten (bv. VCA, BA4/BA5) met **vervaldatum** en PDF. Het dashboard verwittigt tijdig voor ze vervallen.
- Het **jaarlijkse medisch onderzoek** wordt per medewerker bijgehouden.
- Werknemers kunnen zelf een certificaat indienen; de administratie keurt het goed bij **Ingediende certificaten**.

## Gegevens wijzigen als werknemer

Een werknemer vraagt een wijziging (bv. nieuw adres) aan via **Mijn gegevens**. De administratie bevestigt ze bij **Gewijzigde gegevens**.

## Privacy

Gevoelige gegevens zoals het rijksregisternummer worden **versleuteld** bewaard en zijn enkel zichtbaar met de juiste rechten.
MD,
        ],

        'leave' => [
            'title' => 'Verlof, overuren en afwezigheden',
            'body' => <<<'MD'
## Waarvoor?

Verlof aanvragen en goedkeuren, overuren opnemen als betaald verlof, ziekte melden met een attest, en de feestdagen en inhaalrustdagen.

## Waar?

- **Personeel → Mijn verlof**: je eigen aanvragen en je saldo.
- **Personeel → Mijn afwezigheden**: je ziekte en andere afwezigheden.
- **Personeel → Verlofaanvragen**: goedkeuren of weigeren (met het recht).
- **Personeel → Verlofkalender**: wie wanneer afwezig is.
- **Personeel → Afwezigheden**, **Feestdagen** en **Inhaalrustdagen (IR)**: voor de administratie.

## Verlof aanvragen

1. Ga naar **Mijn verlof** en klik op **Verlof aanvragen**.
2. Kies de **soort**: gewoon verlof of **overuren als verlof**.
3. Kies de periode (eventueel een halve dag). Je ziet meteen hoeveel dagen het telt en wat je saldo daarna is; weekends en feestdagen tellen niet mee.
4. Verstuur de aanvraag. Wie mag goedkeuren, krijgt een melding.

Een aanvraag die nog niet behandeld is, kan je **intrekken**.

## Overuren als verlof

Met de module **Prestaties** worden je meeruren bijgehouden. Je ziet bij de aanvraag hoeveel uur je nog kan opnemen. Ook deze aanvragen worden goedgekeurd door iemand met het recht.

## Goedkeuren

Bij **Verlofaanvragen** keur je een aanvraag goed of weiger je ze (met een reden). De werknemer krijgt een melding. Goedgekeurde dagen worden **geblokkeerd in de planning** en automatisch ingevuld in de **prestatiefiche**.

## Ziekte melden

- Via **Mijn afwezigheden → Ziekte melden** (of in de mobiele app). De administratie wordt meteen verwittigd.
- Voeg het **attest** toe als foto of PDF, meteen of later.
- Bij **Afwezigheden** ziet de administratie welke ziekteperiodes nog **zonder attest** zijn.

## Tips

Collega's zien in de kalender enkel "afwezig", nooit de reden van een ziekte.
MD,
        ],

        'timesheets' => [
            'title' => 'Prestaties (prestatiefiche)',
            'body' => <<<'MD'
## Waarvoor?

De dagelijkse **prestatiefiche**: uren, kilometers en projecten per dag, met een wekelijkse PDF per e-mail.

## Waar?

- **Personeel → Prestatiefiche**: je eigen fiche (ook in de mobiele app).
- **Personeel → Overzicht prestaties** en **Prestaties per maand**: voor wie alle fiches mag zien.

## Een dag invullen

1. Open de dag in je prestatiefiche.
2. Vul per regel in: **start in het magazijn**, **werk op de COW-site**, eventueel **extra sites**, en **terug in het magazijn / naar huis**, met uren en kilometerstanden.
3. Kies bij **Imputatie** het project en vul de **omschrijving** in (COW-code of werf).
4. Met **"Invullen vanuit planning"** zet je de projecten van je planning van die dag er meteen in.
5. De **middagpauze** wordt van de gewerkte uren afgetrokken.

Enkel de start, de site en het einde van de dag tellen mee voor de uren; extra sites tussendoor niet.

## Automatisch ingevuld

Verlof, ziekte, opleidingen en feestdagen worden **automatisch** in de fiche gezet.

## De week

- Bij **Weekinstellingen** staan de te werken uren en de **overgedragen** kilometers en meeruren van de vorige week.
- De **week-PDF** wordt elke week automatisch gemaild naar de administratie en naar de werknemer zelf. Met het recht kan je een week ook manueel versturen.

## Meeruren

Wat je meer werkt dan de te werken uren, komt bij je **meeruren-saldo**. Dat saldo kan je als verlof opnemen (zie "Verlof, overuren en afwezigheden").
MD,
        ],

        'hr-calculations' => [
            'title' => 'HR-berekeningen: voorschotten',
            'body' => <<<'MD'
## Waarvoor?

Het **maandelijkse voorschot** van arbeiders en bedienden berekenen.

## Waar?

**Personeel → HR-berekeningen: voorschotten**. Enkel voor rollen met het recht **HR-berekeningen → Bekijken**; beheerders krijgen dit recht niet automatisch, het moet aangevinkt worden.

## De berekening

Voorschot = **startwaarde × gewerkte dagen ÷ werkdagen** in de periode (weekends tellen niet mee).

- Enkel werknemers uit het **personeelsregister**, in dienst en **niet als interim**.
- Startwaarde: **€ 620** voor arbeiders, **€ 1000** voor bedienden (en kaderleden).
- Periode: standaard de **1ste tot en met de 15de** van de maand.
- **Telt als gewerkt**: gewone prestaties, ziekte, feestdagen, inhaalrustdagen (IR), recuperatie overuren en opleidingen.
- **Telt niet**: onbetaald verlof, familiaal verlof en goedgekeurd verlof. Is er maar **één verlofdag** in de periode, dan telt die wel als gewerkt.

## Alles aanpasbaar

- Kies de **maand**, pas de **periode** (van – tot en met) aan.
- Pas de **startwaarde** per statuut aan, of per werknemer in de lijst.
- Met het recht **HR-berekeningen → Beheren** bewaar je de startwaarden en de laatste dag van de periode als **standaard** voor de volgende maanden.
- Exporteer het overzicht naar **PDF**.

Onbetaald verlof registreer je bij **Personeel → Afwezigheden** (soort *Onbetaald verlof*).
MD,
        ],

        'planning' => [
            'title' => 'Planning en opleidingen',
            'body' => <<<'MD'
## Waarvoor?

Een planningskalender per werknemer: wie werkt wanneer aan welk project, in het magazijn of aan iets anders.

## Waar?

- **Planning → Planning**: de kalender (week of maand, als kalender of als lijst).
- **Planning → Opleidingen**: opleidingen plannen.

## Wat zie je?

- Altijd je **eigen planning**, die van je **ploeggenoten** en van collega's die hun planning met je delen.
- Projectleiders zien ook de planning op hun projecten.
- Planners en beheerders zien iedereen.

## Inplannen

1. Klik op **Inplannen** (of op een dag bij een werknemer).
2. Kies een of meer **werknemers**, de datum en eventueel **tot en met** (één item per dag; weekends enkel als je dat aanvinkt).
3. Kies een **tijdslot** of eigen uren.
4. Kies de **soort**: een **project** (enkel open projecten: te plannen, ingepland, uit te voeren of in uitvoering), **magazijnwerk** (omschrijving en taken) of **iets anders**.
5. Vink eventueel **"Ook per e-mail melden"** aan.

Dagen met **verlof, een afwezigheid of een opleiding** zijn geblokkeerd; overlappende tijdsloten worden overgeslagen en gemeld.

## Meldingen

Elke wijziging in je planning krijg je als melding in het platform en op je telefoon (mobiele app), en naar keuze per e-mail.

## Outlook-agenda

Staat de koppeling met Microsoft 365 aan (**Instellingen → Planning → Outlook-agenda**), dan komt elk planningsitem ook in de **Outlook-agenda** van de werknemer, als **volledige dag** (zonder uren), met het project, het adres en de opmerkingen. Wordt het item gewijzigd, verplaatst naar een collega of verwijderd, dan gebeurt dat ook in Outlook.

Bij een **604-project** tonen de planning en Outlook de batterijen die weggaan en bijkomen in plaats van de projectomschrijving, bv. *2 x 500Ah OPzS -> 2 x 490Ah OPzV* (uit het batterijdossier; zolang er geen dossier is, enkel de nieuwe batterijen uit de BAE-bestelling). Wordt het dossier later ingevuld of gewijzigd, dan worden de geplande afspraken in Outlook bijgewerkt.

## Rechten

- **Iedereen inplannen** (projecten en magazijn), **andere items inplannen**, **ieders planning zien** en **opleidingen plannen** zijn aparte rechten.
- Projectleiders plannen hun eigen projecten.
- Wie een **GPW** aanvroeg, plant na goedkeuring een ploeg in binnen de goedgekeurde periode (zie "GPW-aanvragen").
MD,
        ],

        'fleet' => [
            'title' => 'Wagenpark',
            'body' => <<<'MD'
## Waarvoor?

Een overzicht van alle **voertuigen** met hun gegevens en keuringen.

## Waar?

**Wagenpark → Voertuigen**.

## Zo werkt het

- Registreer per voertuig de **nummerplaat, het merk, type, chassisnummer, bouwjaar, brandstof, kilometerstand** en de **keuringsdata**, eventueel met een foto.
- Koppel het voertuig aan een **locatie**, een **ploeg** en een **bestuurder**.
- Het **dashboard** verwittigt op voorhand wanneer een keuring vervalt (het aantal dagen stel je in bij Instellingen → Algemeen).

## Rechten

Bekijken, aanmaken, bewerken en verwijderen zijn aparte rechten.
MD,
        ],

        'assets' => [
            'title' => 'Materieel en de jaarlijkse controle',
            'body' => <<<'MD'
## Waarvoor?

Elektrische arbeidsmiddelen en ander materieel bijhouden, met keuringen, een logboek voor schade en herstellingen, en een jaarlijkse controle per ploeg.

## Waar?

- **Materieel → Materieel**: alle stukken materieel.
- **Materieel → Schade & herstellingen**: het logboek.
- **Materieel → Jaarlijkse controle**: de controlelijsten per ploeg.
- **Materieel → Nieuw materieel te bevestigen**: materieel dat ploegen zelf toevoegden.
- **Materieel → Categorieën**.

## Materieel

Elk stuk heeft een categorie, een serienummer, een status, een plaats (ploeg, voertuig, persoon of locatie) en een **keuringsdatum**. Het dashboard toont keuringen die binnenkort vervallen. Schade en herstellingen noteer je in het **logboek**.

## De jaarlijkse controle

1. Een beheerder **publiceert** de jaarlijkse controle voor een of meer ploegen. Elke ploeg krijgt een lijst van haar materieel (ook het materieel op de ploegvoertuigen) en een melding.
2. De ploeg vult per stuk het **resultaat** in, met een opmerking, op de computer of in de **mobiele app** (tabblad Controles).
3. Materieel dat de ploeg heeft maar niet op de lijst staat, voegt ze toe met **"Materieel toevoegen"**. Een beheerder bevestigt het bij *Nieuw materieel te bevestigen*.
4. Met **Nieuw materieel toevoegen** vult de beheerder een open lijst aan; met **Sluiten** wordt de lijst afgesloten (de resultaten blijven bewaard).

## Rechten

Bekijken, aanmaken, bewerken en verwijderen, en het publiceren of sluiten van de jaarlijkse controle zijn aparte rechten.
MD,
        ],

        'orders' => [
            'title' => 'Bestelnummers',
            'body' => <<<'MD'
## Waarvoor?

Interne **bestelnummers** voor aankopen, zoals `466/1026/JL`, zodat elk nummer maar één keer gebruikt wordt.

## Waar?

**Aankoop → Bestelnummers**.

## Zo werkt het

- Klik op **"+1 Nieuw bestelnummer"** om het volgende vrije nummer te nemen. Het nummer staat meteen op jouw naam (met je initialen).
- Vul aan waarvoor het nummer dient: **leverancier**, **project** en omschrijving.
- In de lijst zoek je terug welk nummer door wie en waarvoor gebruikt werd.
MD,
        ],

        'stock' => [
            'title' => 'Magazijn en voorraad',
            'body' => <<<'MD'
## Waarvoor?

De **artikelen** in het magazijn met hun voorraad **per locatie**, QR-etiketten, fabrikanten, groothandels, categorieën en eenheden.

## Waar?

**Magazijn → Artikelen**, met de stamgegevens: **Artikelcategorieën**, **Eenheden**, **Fabrikanten**, **Groothandels** en hun **Contactpersonen**.

## Voorraad bijhouden

Op een artikel kies je de locatie en daarna:

- **Bijleggen**: er komt voorraad bij.
- **Nemen**: je neemt iets uit het magazijn (eventueel met een notitie, bv. de werf).
- **Tellen**: geef in wat er echt ligt; het verschil wordt gelogd. Zo doe je een **inventaris**.

Elke wijziging staat in de **historiek**. Artikelen onder de minimumvoorraad verschijnen bij **Lage voorraad** op het dashboard.

## QR-etiketten

Druk een **QR-etiket** af per artikel of meerdere tegelijk. Wie het etiket scant, komt meteen bij het artikel.

## Prijzen en afbeeldingen

- Bij een groothandel met een prijskoppeling haal je met **"Prijs ophalen bij groothandel"** de actuele prijs op (een referentie van de groothandel of fabrikant is nodig).
- Een productfoto voeg je toe met **"Afbeelding van webadres"**.
- De speciale prijzen van de groothandels zijn enkel zichtbaar met het recht **Prijzen (speciale prijzen groothandels) → Bekijken**.
MD,
        ],

        'it' => [
            'title' => 'IT-beheer',
            'body' => <<<'MD'
## Waarvoor?

Alle IT-middelen, zoals in Snipe-IT: **hardware, licenties, accessoires, verbruiksgoederen en onderdelen**, met uitlenen en innemen, audits en onderhoud.

## Waar?

- **IT → Hardware, Licenties, Accessoires, Verbruiksgoederen, Onderdelen, Onderhoud**.
- Stamgegevens: **Modellen, IT-categorieën, IT-fabrikanten, IT-leveranciers, Statuslabels**.
- **IT → Mijn IT**: elke werknemer ziet wat aan hem uitgeleend is.

## Zo werkt het

- **Uitlenen** aan een gebruiker, een locatie of een ander toestel, eventueel met een datum *verwacht terug*. **Innemen** als het terugkomt.
- **Licenties** hebben plaatsen (seats) die je toewijst; je ziet hoeveel er nog vrij zijn en welke licenties binnenkort vervallen.
- **Onderdelen** bouw je in een toestel in; **verbruiksgoederen** geef je uit.
- Een **audit** bevestigt dat een toestel gezien is en plant de volgende.
- Bij **Onderhoud** noteer je herstellingen en upgrades.

## Rechten

Per soort (hardware, licenties, overige items) zijn er rechten voor bekijken, aanmaken, bewerken, verwijderen, uitlenen en auditen. Productsleutels van licenties zie je enkel met het recht **Productsleutels zien**.
MD,
        ],

        'work-sites' => [
            'title' => 'Werflocaties (COW-codes)',
            'body' => <<<'MD'
## Waarvoor?

De werflocaties van de klant, elk met een **COW-code** (bv. `02GAM`), adres, gebouwtype, area, contactpersoon, foto's en opmerkingen.

## Waar?

**Werflocaties → Werflocaties**, **Areas** en **Contactpersonen**.

## Werflocaties

- Zoek op COW-code, straat of gemeente; open het adres in **Waze** of **Google Maps**.
- Het **gebouwtype** wijzig je met **"Gebouwtype wijzigen"**: elke wijziging komt met datum en reden in het **logboek**.
- Voeg **foto's** en **opmerkingen** toe (opmerkingen met het juiste recht).

## Areas en contactpersonen

- Een **area** groepeert werflocaties en heeft **een of meer contactpersonen**.
- Een werflocatie kan een eigen contactpersoon hebben. Heeft ze er geen, dan gelden de contactpersonen van haar area.
- Een contactpersoon heeft een **taal** (NL, FR of EN): mails zoals een GPW-aanvraag gaan in die taal.

## Waar wordt het gebruikt?

Projecten krijgen hun COW-code van de werflocatie (bv. `604-0001-02GAM`). Laadpalen, routers, interventies en GPW-aanvragen werken met dezelfde werflocaties en contactpersonen.
MD,
        ],

        'projects' => [
            'title' => 'Projecten',
            'body' => <<<'MD'
## Waarvoor?

Alle projecten per **categorie** (600, 604, 605, 606, 608, 610, …) met hun PO-nummers, bestanden, foto's, plannen, schema's, onderdelenlijst, offertes en facturen.

## Waar?

**Projecten → Projecten**; de categorieën bij **Projecten → Projectcategorieën** (beheer).

## Projectnummer

- Het nummer volgt de categorie: categorie, volgnummer en COW-code, bv. `604-0001-02GAM`; jaarlijks genummerd zoals `605-260001-02GAM`; particulier zonder COW-code, bv. `606-0001`.
- Laat het nummer leeg voor het **volgende vrije nummer**. Bij een categorie kan de beheerder het **volgende nummer** instellen; op een bestaand project kan je het nummer corrigeren.

## Status

Offerte → Wacht op PO → Te plannen → Ingepland → Uit te voeren → In uitvoering → Uitgevoerd → Te factureren → Gefactureerd → Afgesloten. Wijzig de status met **"Status wijzigen"**. Een **afgesloten** project wordt gearchiveerd met een ZIP van alle bestanden.

## Op een project

- **PO-nummers** van de klant (10 cijfers, meerdere mogelijk).
- Tabbladen voor **documenten, foto's, plannen, schema's, extra bestanden, onderdelen, offertes en facturen**. Bestanden hebben versies.
- **Projectleiders** en **toegewezen ploegen** bepalen wie het project ziet.
- Voor 610–650 toont een **voortgangsbalk** de stappen (aanvraag, werfbezoek, offerte, PO, … tot overgedragen); vink stappen aan of ze worden automatisch aangevinkt. Is **PO ontvangen** aangevinkt, dan voeg je met **"PO-nummer toevoegen"** op elk moment een PO-nummer toe (bij het aanvinken zonder PO-nummer opent dat venster meteen).
- **Kopiëren** start een nieuw project vanuit dit project.
- Bij 608 kies je de **locatie**: een COW-code of een **derde klant** met een eigen installatieadres.
- Vanuit een project vraag je een **GPW** aan (zie "GPW-aanvragen").

## Rechten

Aparte rechten voor bekijken, aanmaken, bewerken, verwijderen, bestanden, onderdelen, PDF's, kopiëren en GPW aanvragen. Offertes en facturen zie je enkel met de financiële projectrechten of als projectleider.
MD,
        ],

        'gpw' => [
            'title' => 'GPW-aanvragen',
            'body' => <<<'MD'
## Waarvoor?

De klant toestemming vragen om in een bepaalde **periode** op een site te werken, en na goedkeuring een ploeg inplannen in die periode.

## Een GPW aanvragen

1. Open een project met de status **Uit te voeren**.
2. Klik op **"GPW aanvragen"**. Je ziet naar wie de aanvraag gaat.
3. Kies de **periode** (van – tot en met) en vul eventueel **opmerkingen** in (toegang, sleutels, onderbrekingen).
4. Klik op **Aanvraag versturen**.

De aanvraag gaat per e-mail naar de **contactperso(o)n(en) van de COW-site** (of van de area), elk in **zijn eigen taal**, met jou in kopie. Heeft de site geen contactpersoon met e-mailadres, dan vul je die eerst in bij de werflocatie of area.

## De klant keurt goed

- Via de **link in de mail**: de klant ziet de aanvraag en klikt op **Goedkeuren** of **Weigeren** (met een reden).
- Of in de **Global overview for Client** (het klantenportaal), tabblad **GPW-aanvragen**.

Bij goedkeuring gaat het project naar **Te plannen** en krijg jij een melding in het platform, op je telefoon en per e-mail.

## Inplannen

In het tabblad **GPW-aanvragen** van het project klik je bij de goedgekeurde aanvraag op **Inplannen**: kies een **ploeg**, de dagen (enkel binnen de goedgekeurde periode) en de uren. Alle leden van de ploeg komen in de planning; het project gaat naar **Ingepland**, en de klant ziet dat met de periode in zijn portaal.

## Rechten en sjablonen

- Aanvragen: het recht **Projecten → GPW aanvragen**.
- De mails (aanvraag, goedgekeurd, geweigerd) pas je aan bij **E-mailsjablonen**.
MD,
        ],

        'interventions' => [
            'title' => 'Interventies (600)',
            'body' => <<<'MD'
## Waarvoor?

Interventies op een COW-code in categorie **600**, met een **WO-nummer**, interventiecategorieën, een afsluitrapport en een review door de beheerder.

## Waar?

- **Interventies → Interventies**: de lijst.
- **Interventies → Reviews** en **Interventiecategorieën**: aan de beheerkant.

## Een interventie aanmaken

1. Maak een project in categorie **600** (of klik op *Nieuwe interventie*). Het start meteen als **Uit te voeren**.
2. Vul het **WO-nummer** in (uniek), de COW-site, de **interventiecategorieën** en de subcategorie met fase en tags.
3. Beheerders en wie het recht heeft, kunnen meteen een **PO-nummer** ingeven, en dat ook later nog op elk moment doen met **"PO-nummer toevoegen"**.

## Afsluiten

De ploeg sluit de interventie af met een **afsluitrapport**: gebruikt en vrijgekomen **materiaal**, de **zekering/automaat-posities**, borden, uren en medewerkers. De interventie gaat dan naar review.

## Review

De beheerder **keurt goed** met het PO-nummer (een PO-nummer van het project wordt voorgesteld) of **stuurt terug** met een reden. Na goedkeuring gaat het project naar **Te factureren** en wordt de **PDF** gemaakt.
MD,
        ],

        'batteries' => [
            'title' => 'Batterijdossiers (604)',
            'body' => <<<'MD'
## Waarvoor?

Het volledige dossier voor het vervangen van batterijen op **604-projecten**: input, leveringsnota, meetrapport, visueel keuringsverslag, plattegrond, eendraadschema, verzendnota en factuuraanvraag, alles als PDF.

## Waar?

- **Batterijen → Batterijdossiers**, of via de knop **Batterijdossier** op een 604-project.
- **Batterijen → Overzicht levering batterijen**: de leveringen per project.
- **Batterijen → Batterijtypes** en **Frötek-kabels**: de stamgegevens.

## Zo werkt het

1. Open het dossier vanuit het project; de projectgegevens en het PO-nummer worden ingevuld.
2. Vul de tabbladen in: **input** (batterijtype, aantallen, kabels), de **metingen** en de **visuele keuring**.
3. Laat het dossier **ondertekenen** (op de computer of in de mobiele app, met de vinger).
4. Genereer de **PDF's** van de verschillende documenten.

## Leveringen

Bij **Overzicht levering batterijen** volg je per project de orderbevestiging, het type, het aantal en de geplande leveringsdatum op, en vink je **geleverd** aan.

In het klantenportaal ziet de klant per levering of ze **geleverd** is met de **werkelijke leverdatum** (de dag van het vinkje), wanneer de **installatie gepland** is (de datum uitvoering van het batterijdossier, of anders de eerste dag in de planning) en een vinkje **uitgevoerd** met de datum waarop het project naar *uitgevoerd* ging.

Met **Documenten** voeg je per levering drie bestanden toe: de **bestelling** aan de leverancier, de **orderbevestiging** van de leverancier en de **leveringsbon** bij aankomst. Ze staan ook op het project (tabblad *Batterijleveringen*). Ze zijn intern: nooit zichtbaar in het klantenportaal, en enkel met de rechten **Leveringsdocumenten zien en downloaden** en **Leveringsdocumenten uploaden**.

## BAE-bestelling

Elk nieuw 604-project krijgt meteen zijn **BAE-bestelling** (de vroegere *calc.xlsx*). Open ze via **Batterijen → BAE-bestellingen** of met de knop **BAE-bestelling** op het project (oudere projecten krijgen ze dan ook).

1. Kies het **type batterij** en de **stelling**: de bestellijnen (intercell-, step-, tier- en row-connectoren), het rek met zijn prijs en de BAE-prijs per element worden ingevuld uit **Batterijtypes**.
2. Vul het **aantal batterijen**, de elementen per batterij en het vermogen in, of neem ze over met **Overnemen uit batterijdossier**.
3. Kijk het **overzicht** onderaan na: het rekent zoals het Excel-blad *Order* (batterijen × elementen × prijs per element, plus de rekken).
4. **Exporteren naar PDF** slaat op en opent de bestelling, klaar om naar BAE te sturen. Met **Bewaren bij leveringsdocumenten** komt de PDF bij de levering als *Bestelling aan de leverancier*.

5. **Versturen naar leverancier** mailt de bestelling als PDF naar de gekozen **leverancier**, met een optioneel extra bericht. Dat gebeurt enkel met deze knop, nooit automatisch, en pas als de bestelling volledig is ingevuld. In de lijst zie je wanneer en naar wie ze verstuurd werd.

Leveranciers beheer je bij **Batterijen → Leveranciers** (recht **Batterijleveranciers beheren**): het e-mailadres voor bestellingen, extra adressen in kopie, de taal van de mail en welke leverancier standaard is. Elke bestelling gaat ook in kopie naar de adressen bij **Instellingen → Batterijen → BAE-bestelling → Bestellingen in kopie naar**. De tekst van de mail pas je per taal aan bij **E-mailsjablonen → BAE-bestelling (aan leverancier)**.

De standaardwaarden (contract, leveradres, contactpersoon, facturatie) staan in **Instellingen → Batterijen → BAE-bestelling**. De BAE-prijzen en connectoren pas je aan bij **Batterijtypes**. Enkel met het recht **BAE-bestellingen: zien, invullen en exporteren** (en *PDF genereren* voor de export).

## Rechten

Bekijken, aanmaken, bewerken, verwijderen, het **input**-tabblad, **prijzen & factuuraanvraag**, PDF's en het wijzigen van afgeronde dossiers zijn aparte rechten.
MD,
        ],

        'enersys' => [
            'title' => 'Enersys-batterijen (605)',
            'body' => <<<'MD'
## Waarvoor?

Dossiers voor **Enersys-batterijen** op **605-projecten**: input, leveringsnota, meetrapport, visueel keuringsverslag, vervoersdocument en bestelling, met handtekeningen en PDF.

## Waar?

**Batterijen → Enersys-batterijen**, of via de knop op een 605-project.

## Zo werkt het

Het werkt zoals de batterijdossiers (604): open het dossier vanuit het project, vul de input, metingen en keuring in, laat het ondertekenen (ook in de mobiele app) en maak de PDF's.


## Leveringen opvolgen

De klant bestelt de Enersys-batterijen zelf. Bij **Batterijen → Enersys-leveringen** volg je per 605-project het **PO-nummer** en de **leverdatum** op en vink je **geleverd** aan bij aankomst. Het project gaat dan naar **In te plannen** (tenzij het al verder staat, bv. ingepland). Het PO-nummer wordt overgenomen van het project. De leveringen staan ook op het project (tabblad *Opvolging leveringen*).

## Rechten

Dezelfde soort rechten als bij de batterijdossiers, apart in te stellen onder **Enersys-batterijen**.
MD,
        ],

        'board_deliveries' => [
            'title' => 'Opvolging elektrische borden (610–650)',
            'body' => <<<'MD'
## Waarvoor?

De opvolging van de **elektrische borden** van de 610-, 620-, 630-, 640- en 650-projecten, met een eigen lijst per **leverancier**: ELP, P&V panels, CE+T en Automation LP (Halle). Het is een aparte module die je bij **Instellingen → Modules** aan- of uitzet.

## Waar?

- **Borden → Opvolging borden**: één tabblad per leverancier.
- **Borden → Bordleveranciers**: leveranciers toevoegen, de volgorde en de soort lijst kiezen.
- Op het project: tabblad *Opvolging leveringen*.

## Zo werkt het

- **ELP en P&V panels** (eenvoudige lijst): project, PO-nummer, leverdatum en een vinkje **geleverd** (de dag van het vinkje wordt de werkelijke leverdatum).
- **CE+T en Automation LP** (lijst met configuratie): PO-nummer, project, **verwachte levering**, **werkelijke levering**, **configuratiedatum** en een vinkje **geplaatst & geconfigureerd**. De datums vul je rechtstreeks in de lijst in.

Een nieuwe lijn maak je met **Nieuw**: kies de leverancier en een 610–650-project; het PO-nummer wordt van het project overgenomen. De projectstatus verandert hier nooit vanzelf.

## Rechten

**Opvolging elektrische borden**: bekijken, aanmaken, bewerken, verwijderen en **leveranciers beheren**.
MD,
        ],

        'charging' => [
            'title' => 'Laadpalen en 4G-routers',
            'body' => <<<'MD'
## Waarvoor?

De gegevens van de **laadpalen** (608-projecten) en van de **4G-routers** per COW-site, met wachtwoorden en VPN-bestanden die enkel met het juiste recht zichtbaar zijn.

## Waar?

**Laadpalen → Laadpalen** en **Laadpalen → 4G-routers**. Op een 608-project staat ook een tabblad met zijn laadpalen.

## Een laadpaal toevoegen

1. Kies de **locatie**: **COW-code** (een site uit de lijst) of **Andere (derde klant)** met de klantgegevens en het volledige **installatieadres**.
2. Kies eventueel het **project**: de locatie wordt dan overgenomen.
3. Vul de gegevens in: ACE-nummer, type (standalone of cluster), systeem, merk, connectiviteit, licentie, SIM, status.
4. Wachtwoord en herstelcode vul je in met het recht **Vertrouwelijke gegevens zien**.

## Meerdere laadpalen tegelijk

Met **"Meerdere laadpalen toevoegen"** vul je de locatie en de status één keer in en de laadpalen elk op een eigen **rij**. Met het kopieerpictogram dupliceer je een rij.

## Export

Exporteer de lijst naar **Excel** of **PDF**, met of zonder vertrouwelijke gegevens (enkel met het recht).

## 4G-routers

Per COW-site het type, serienummer, SIM-nummers, login en het **VPN-bestand**. Login en VPN-bestand zijn enkel zichtbaar met het recht **Vertrouwelijke gegevens zien** voor routers.
MD,
        ],

        'asbuilt' => [
            'title' => 'As-builts',
            'body' => <<<'MD'
## Waarvoor?

Per project een **as-built** met alle bestanden per categorie, de keuring door **Normec BTV** en op het einde één ZIP voor de klant.

## Waar?

- **Projecten → As-builts**: het overzicht per status (in uitvoering, gepland voor Normec BTV, afgerond).
- **Projecten → As-built standaardbestanden**: het register van standaardbestanden.
- Op een project: de knop **As-built**.

## Hoe ontstaat een as-built?

Zodra een project de status **Uitgevoerd** krijgt, wordt de as-built automatisch aangemaakt met de status **In uitvoering**. Niet voor de uitgesloten categorieën (standaard 600, 604, 605, 606, 607 en 608.1; in te stellen bij Instellingen → Algemeen). De **standaardbestanden** van de COW-code van het project, de externe invloeden en de algemene standaardbestanden worden meteen toegevoegd.

Met de knop **As-built aanmaken** op het project (recht *As-built → Beheren*) maak je de as-built al vroeger aan, met dezelfde standaardbestanden en werkwijze. Wordt het project daarna *Uitgevoerd*, dan wordt er geen tweede aangemaakt. Bij elke as-built staat of hij automatisch of manueel (en door wie) werd aangemaakt.

## Categorieën

Plannen, elektrische schema's, evacuatiewegen, externe invloeden, technische documenten, kabeldimensionering, BTV-keuringsverslagen, ICC- en Lmax-berekeningen. Elk bestandsformaat is toegelaten.

- **Evacuatiewegen** horen bij een **COW-code** en worden hergebruikt in elke as-built van die COW-code.
- **Externe invloeden** gelden voor **alle projecten**: ze horen bij geen COW-code en komen in elke as-built.
- **Technische documenten** vormen een **register**: kies er per as-built uit en upload nieuwe (die komen ook in het register).

## Werkwijze

1. Voeg bestanden toe per categorie (de uploadknop bij elke categorie of **Bestanden toevoegen**).
2. **Keuring Normec BTV plannen**: kies de datum. De status wordt *Gepland voor Normec BTV*.
3. Na de keuring: **Keuringsverslag BTV uploaden**. De as-built sluit automatisch af en wordt een ZIP met de naam *"610-012-02GAM - ASBUILT"* met een map per categorie (0 - BTV control, 1 - cable dimensions, … 8 - evacuation routes).
4. De klant krijgt een **e-mail** en downloadt de ZIP in de Global overview for Client.

Met het recht **Heropenen** zet je een afgeronde as-built terug open.

## De klant

In het klantenportaal (recht **As-builts**) volgt de klant de status op, downloadt hij de ZIP en levert hij bestanden aan: evacuatiewegen, plannen, externe invloeden, ICC en Lmax, of alles in één ZIP in de **algemene zone**. De projectleiders krijgen daarvan een melding.

## Rechten

- **As-built → Bekijken**: het overzicht van alle as-builts.
- **As-built → Beheren**: bestanden toevoegen en verwijderen, keuring plannen, BTV-verslag uploaden.
- **As-built → Standaardbestanden beheren** en **Heropenen**.
- **As-built → Verwijderen**: een as-built (met zijn eigen bestanden en ZIP) definitief verwijderen, enkel aan de **beheerderskant**. Dit is een *extra recht*: ook beheerders hebben het pas als het aangevinkt is.
- Wie op het project staat (projectleider of toegewezen ploeg), kan de as-built van dat project altijd openen en downloaden.
MD,
        ],

        'cable-stripping' => [
            'title' => 'Kabel ontmantelen',
            'body' => <<<'MD'
## Waarvoor?

Opvolging van het **ontmantelen van kabel**: per lijn de datum, het project, wie eraan werkte, de uren en de wegingen.

## Waar?

**Afval → Kabel ontmantelen**.

## Zo werkt het

- Voeg per partij een **lijn** toe met de datum, de **imputatie** (project), de medewerkers, het **begin- en einduur**, het **baknummer** en de **wegingen**: vóór, de lege bak en na het ontmantelen.
- De **uren**, de **ontmantelde kabel** en het **afval** worden automatisch berekend.
- Maak een **PDF-overzicht** van een periode.
MD,
        ],

        'waste' => [
            'title' => 'Afvalregister',
            'body' => <<<'MD'
## Waarvoor?

Het wettelijke **register van afgevoerd afval** per categorie en EURAL-afvalcode, met de afvalverwerkers, batterijlijsten per gewest en totalen.

## Waar?

**Afval → Afvalregister**, **Totalen per categorie**, **Afvalcategorieën** en **Afvalverwerkers**.

## Zo werkt het

1. Registreer elke afvoer: datum, **soort afval** (categorie met EURAL-code; een sterretje betekent gevaarlijk afval), **gewicht**, de **afvalverwerker** en zijn referentie (bv. vrachtbriefnummer).
2. Bij **batterijafval** vul je per **vernietigingsattest** een lijn in met het **gewest**, de COW-code, het PO-nummer en het **gewicht**. Eén registratie kan meerdere lijnen hebben (meerdere attesten, COW-codes en PO-nummers); het totale gewicht is de som van de lijnen. Per lijn vul je ook een **projectreferentie** in (een project van het platform, of vrij in te vullen). Per lijn upload je het **PDF-document** van dat attest; dat kan ook later in de batterijlijst per gewest met **PDF uploaden**.
3. **Afval → Batterijlijsten per gewest** toont per gewest een rij per lijn, met het eigen gewicht, ook als meerdere lijnen onder dezelfde referentie vallen. **Exporteren per jaar** maakt eerst de lijst (liggend), gevolgd door de PDF van elke lijn (staand), in dezelfde volgorde.
3. Bij **Totalen per categorie** zie je de totalen per periode.
4. Exporteer elke lijst naar **PDF**, bv. voor een controle of de jaarlijkse aangifte.

## Stamgegevens

Categorieën kunnen subcategorieën hebben; inactieve categorieën en verwerkers kan je niet meer kiezen voor nieuwe registraties.
MD,
        ],

        'finance' => [
            'title' => 'Financieel: facturen, creditnota\'s en inkomende facturen',
            'body' => <<<'MD'
## Waarvoor?

Facturen en creditnota's opmaken en versturen (ook via **Peppol**), betalingsherinneringen, inkomende facturen valideren, klanten beheren en statistieken.

## Waar?

**Financieel → Facturen, Creditnota's, Inkomende facturen, Klanten, Standaard factuurlijnen, Financieel overzicht, Statistieken**. De bedrijfsgegevens en Peppol stel je in bij **Instellingen → Financieel**.

## Facturen

1. Maak een factuur voor een **klant**, eventueel gekoppeld aan een **project** en zijn **PO-nummers**.
2. Voeg lijnen toe (of kies een **standaard factuurlijn**); btw en totalen worden berekend.
3. **Versturen** maakt de PDF met gestructureerde mededeling en mailt ze (sjabloon *Factuur*), of stuurt ze via Peppol als de klant een Peppol-ID heeft.
4. Voor vervallen facturen verstuur je tot **drie betalingsherinneringen**; zet je ze aan bij **Instellingen → Financieel**, dan gaan ze elke dag automatisch.

Een **creditnota** maak je vanuit een factuur of apart.

## Inkomende facturen

Inkomende facturen (ook via Peppol ontvangen) worden **gevalideerd**: goedgekeurd of afgewezen, met een handtekening.

## Overzicht en statistieken

- **Financieel overzicht**: per maand de inkomsten, inkomende facturen en het resultaat; klik op een maand voor de documenten.
- **Statistieken**: per periode, met vergelijking met vorig jaar.

## Rechten

Werknemers zien dit enkel met de financiële rechten (per soort: facturen, creditnota's, inkomende facturen, klanten).
MD,
        ],

        'incidents' => [
            'title' => 'Incidenten melden en opvolgen',
            'body' => <<<'MD'
## Waarvoor?

**Ongevallen, bijna-ongevallen, gevaarlijke situaties en gevaarlijke handelingen** meteen melden, met foto's, zodat ze opgevolgd worden.

## Melden

1. Ga naar **Veiligheid → Incident melden** (of het tabblad Meer in de mobiele app).
2. Kies de **soort** en **waar** het gebeurde (magazijnlocatie of werflocatie, met de exacte plaats).
3. Duid de **gevolgen** aan (andere slachtoffers, materiële schade) en beschrijf wat er gebeurd is.
4. Voeg tot **20 foto's** toe en verstuur. Datum en tijd worden automatisch toegevoegd.

De beheerders worden meteen verwittigd. Je eigen meldingen zie je bij **Mijn meldingen**.

## Opvolgen

Bij **Veiligheid → Incidenten** noteren beheerders de **opvolging** (genomen maatregelen, wie verwittigd is) en exporteren ze een **PDF**, ook per medewerker of per periode.
MD,
        ],

        'suggestions' => [
            'title' => 'Ideeënbus',
            'body' => <<<'MD'
## Waarvoor?

**Ideeën, klachten en andere opmerkingen** indienen bij de directie.

## Indienen

1. Klik bovenaan op **Ideeënbus** (in de bovenbalk) of gebruik de mobiele app.
2. Kies **idee, klacht of andere**, schrijf je bericht en voeg eventueel foto's toe.
3. Zet **"Mag binnen het bedrijf gedeeld worden"** aan als je inzending met collega's gedeeld mag worden.

## Het gesprek

- Je inzendingen en de antwoorden vind je bij **"Mijn inzendingen"** (knop op de pagina van de ideeënbus). Klik op een inzending om het gesprek te openen.
- Beheerders reageren bij **Veiligheid → Ideeënbus** met **"Reageren"**; jij antwoordt op dezelfde manier. Een reactie bevat tekst en eventueel **foto's**.
- Bij elke nieuwe reactie krijgt de andere kant een **melding in het platform** en een **e-mail** in zijn eigen taal (sjabloon *Ideeënbus: nieuwe reactie*).
- Enkel jij en de beheerders zien het gesprek en de foto's.

## Opvolgen

Beheerders zetten de **status** (nieuw, in behandeling, afgerond) met **Opvolgen** en exporteren de inzendingen naar **PDF**. De eerste reactie van een beheerder zet een nieuwe inzending op *in behandeling*.
MD,
        ],

        'toolbox' => [
            'title' => 'Toolboxmeetings',
            'body' => <<<'MD'
## Waarvoor?

**Veiligheidsinstructies** met tekst en bijlagen die elke werknemer volledig leest en ondertekent.

## Waar?

- **Veiligheid → Toolboxmeetings**: lezen, ondertekenen en (met het recht) aanmaken.
- **Veiligheid → Opvolging toolboxmeetings**: wie welke toolbox ondertekende.

## Een toolboxmeeting maken

1. Klik op **Nieuw** en vul de titel, de maand, wie de instructies gaf en het onderwerp in het **Nederlands en Frans** in.
2. Schrijf de tekst met de editor: titels, opsommingen en **afbeeldingen** die je vrij in de tekst plaatst.
3. Voeg **bijlagen** toe (PDF, PowerPoint, Word, afbeeldingen).
4. **Publiceer**: alle werknemers zien de toolbox en krijgen een melding (ook op hun telefoon).

## Lezen en ondertekenen

- Open de toolbox en **lees de hele tekst** (scroll tot onderaan) en/of **open alle bijlagen**.
- Pas daarna kan je **ondertekenen** met je handtekening. Je naam en handtekening worden bewaard.
- Dat kan ook in de **mobiele app**.

## Opvolging en PDF

- Bij **Opvolging toolboxmeetings** zie je per maand en per toolbox wie ondertekende (✓).
- De **PDF** is de aanwezigheidslijst met titel, bedrijf, maand, instructeurs, onderwerp (NL/FR) en de namen en handtekeningen.
MD,
        ],

        'knowledge-base' => [
            'title' => 'Kennisbank',
            'body' => <<<'MD'
## Waarvoor?

Interne **artikels en downloads** per categorie, in het Nederlands, Frans en Engels. Ook deze handleiding staat in de kennisbank.

## Lezen

Ga naar **Kennisbank → Kennisbank**. Kies een categorie of zoek op een woord. Je leest de artikels in **jouw taal**; bijlagen download je onderaan het artikel.

## Schrijven

Met de kennisbankrechten:

- **Kennisbank → Categorieën**: categorieën met naam en beschrijving per taal; verborgen categorieën zien enkel de schrijvers.
- **Kennisbank → Artikels**: titel en tekst **per taal**, als opgemaakte tekst (met afbeeldingen) of in Markdown, met downloadbare bestanden. Een artikel kan je als concept bewaren tot het klaar is.
- Bijlagen in PDF downloaden vereist het recht **Kennisbank → PDF-bestanden downloaden**.
MD,
        ],

        'client-portal' => [
            'title' => 'Klantenportaal (Global overview for Client)',
            'body' => <<<'MD'
## Waarvoor?

Een apart portaal voor de klant (bv. de managers van Proximus) met een **alleen-lezen overzicht** van hun projecten, GPW-aanvragen, batterijleveringen, laadpalen en 4G-routers. **Prijzen worden nooit getoond.**

## Waar?

- Klanten melden zich aan op **/client** met hun eigen account, los van de ERP-gebruikers.
- Beheerders beheren de accounts bij **Beheer → Systeem → Klantenportaal**.

## Een klantgebruiker aanmaken

1. Vul naam, e-mail, bedrijf en **taal** in.
2. Koppel eventueel een **contactpersoon** (zoek op naam of COW-code): de gebruiker ziet de projecten op de COW-sites van die contactpersoon. Voeg ook losse **toegewezen projecten** toe.
3. Vink aan wat hij mag zien: projecten (met de **categorieën**), **GPW-aanvragen**, batterijleveringen, laadpalen, 4G-routers.
4. Laat **"Welkomstmail sturen"** aan: de gebruiker krijgt een mail in zijn taal en kiest zelf zijn wachtwoord.

## Wat ziet de klant?

- Standaard enkel **zijn eigen projecten**; projecten van andere klanten enkel met **"🔒 Alle projecten"**.
- Wachtwoorden, herstelcodes, routerlogins en VPN-bestanden enkel met de 🔒-rechten.
- Laadpalen en routers zonder COW-code (bv. bij een derde klant) verschijnen nooit in het portaal.
- Bij **GPW-aanvragen** keurt hij aanvragen goed of weigert hij ze.

De mails (welkom, nieuw wachtwoord) pas je aan bij **E-mailsjablonen**.

## Account verwijderen

Een klant kan zijn eigen account verwijderen via zijn **profiel → Mijn account verwijderen**. Hij krijgt eerst de waarschuwing dat hij daarna geen toegang meer heeft tot de informatie en downloads, vinkt aan dat hij dat begrijpt en bevestigt met zijn wachtwoord. Wat hij aanleverde blijft bij de projecten. De beheerders krijgen een melding.
MD,
        ],

        'mobile-app' => [
            'title' => 'Mobiele app PI ERP',
            'body' => <<<'MD'
## Waarvoor?

De app **PI ERP** brengt het belangrijkste van het platform naar je telefoon, ook onderweg.

## Installeren en aanmelden

- Op een Android-telefoon stelt het platform voor om de app te downloaden; je vindt de download ook in je **gebruikersmenu**.
- Meld je aan met je **e-mailadres en wachtwoord** van het platform. De app gebruikt de taal die je in het platform koos.

## Wat kan je in de app?

- **Planning**: je planning per dag, week of maand (en die van collega's die je mag zien). Je krijgt een melding bij elke wijziging.
- **Prestaties**: je prestatiefiche invullen; het wordt meteen in het platform bewaard.
- **Verlof**: verlof en **overuren als verlof** aanvragen, **ziekte melden** en een attest toevoegen (foto, bestand of meteen een foto nemen).
- **Controles**: de **jaarlijkse controle** van het materieel van je ploeg invullen (enkel als er een lijst openstaat).
- **Meer**: je gegevens, **toolboxmeetings** lezen en ondertekenen, **incident melden**, de **ideeënbus** en de **batterijdossiers** (604/605) invullen en ondertekenen, met de **plattegrond** die je tekent zoals op het platform (604).

## Meldingen

Sta meldingen toe: je krijgt een melding bij een wijziging in je planning, een nieuwe toolboxmeeting, een jaarlijkse controle of een goedgekeurde GPW.

## Privacy

De privacyverklaring van de app staat op **/privacy** en is ook te openen vanuit de app.
MD,
        ],
    ],
];
