<?php

namespace App\Support;

/**
 * The questions of the "Visueel keuringsverslag" (visual inspection report), in Dutch as on the
 * document Proximus receives. Each is answered with yes (Ja) or no (Nee).
 */
class BatteryInspection
{
    /**
     * Section title => [key => question]. Keys are stored, so they never change.
     *
     * @return array<string, array<string, string>>
     */
    public static function sections(): array
    {
        return [
            'Contole van de cellen of monobloks' => [
                'cells_identical' => 'Zijn alle cellen identiek?',
                'cells_same_date' => 'Zijn de cellen van dezelfde productiedatum',
                'cells_labelled' => 'Zijn de cellen voorzien van een etiket?',
                'label_manufacturer' => 'Etiket: Fabrikant',
                'label_type' => 'Etiket: Type van de cel',
                'label_capacity' => 'Etiket: Capaciteit van de cel',
                'label_float_voltage' => 'Etiket: De vlotspanning van de cel',
                'label_production_date' => 'Etiket: De productiedatum (maand en jaar)',
                'cells_measurable' => 'Is het mogelijk om de individuele spanning van de cellen te meten?',
                'ceramic_vents' => 'Is elke cel voorzien van keramische ventilatiedoppen?',
                'recovery_vents' => 'Is elke cel voorzien van ventilatie- doppen met een recuperatiesysteem?',
            ],
            'Controle van de batterij:' => [
                'flexible_connectors' => 'Werden er soepele intercelverbinders gebruikt?',
                'connectors_insulated' => 'Zijn de intercelverbindingen geïsoleerd (IP20)?',
                'terminal_plates' => 'Zijn er aansluitplaten voor kabels voorzien?',
                'terminal_plates_shielded' => 'Zijn de aansluitplaten voor zien van een afscherming (IP20)?',
                'polarity_marked' => 'Is de pos en negatieve aansluiting gemarkeerd?',
                'battery_numbered' => 'Heeft de batterij een nummer (1, 1A, 2,  …) gekregen',
                'cells_numbered' => 'Heeft elke cel een nummer gekregen (start vanaf positief)',
                'markings_readable' => 'Zijn alle markeringen goed leesbaar?',
                'id_stickers' => 'Zijn de stikkers met de ID nummers aangebracht (2 per batterij)',
                'cleaned' => 'Zijn de cellen, intercelverbinders, … gereinigd?',
            ],
            'De opstelling:' => [
                'stable' => 'Zijn de batterijen stabiel opgesteld?',
                'rack_fits' => 'Is het gestel/kast (in afmetingen) aangepast aan de batterij?',
                'cells_accessible' => 'Zijn alle cellen vlot toegankelijk?',
                'plates_under_rack' => 'Zijn er plaatjes geplaatst onder de stelling of de kast?',
            ],
            'Enkel bij opstelling in kast:' => [
                'doors_open' => 'Zijn de deuren van de kasten volledig te openen? (≥90°)',
                'doors_direction' => 'Draaien ze open naar de goede kant (zie plan) ?',
                'door_battery_number' => 'Markering op de deur: Nummer van de batterij',
                'door_battery_symbol' => 'Markering op de deur: Symbool van een batterij',
                'door_danger_triangle' => 'Markering op de deur: Elektrische gevarendriehoek',
                'door_voltage' => 'Markering op de deur: Bedrijfsspanning',
            ],
            'Zuurbakken' => [
                'acid_trays' => 'Zijn er zuurbakken geplaatst?',
                'acid_trays_welded' => 'Zijn de zuurbakken dubbelzijdig dichtgelast?',
                'acid_trays_clean' => 'Zijn de zuurbakken gereinigd?',
                'acid_trays_space' => 'Is er 10 cm ruimte tussen de binnenste rand van de zuurbak en de batterij?',
            ],
            'Verwijdering oude batterijen' => [
                'old_removed' => 'Werden er oude batterijen verwijderd?',
                'old_cells_removed' => 'Zijn alle cellen verwijderd van het terrein/gebouw?',
                'old_parts_removed' => 'Zijn alle onderdelen verwijderd?',
                'old_rack_removed' => 'Werd de oude stelling volledig verwijderd?',
                'old_place_clean' => 'Is de oude opstellingsplaats opgekuist?',
                'old_maintenance_removed' => 'Oude toebehoren verwijderd: Onderhoudsmateriaal?',
                'old_ppe_removed' => 'Oude toebehoren verwijderd: Persoonlijke beschermingsmiddelen?',
                'old_docs_removed' => 'Oude toebehoren verwijderd: Documentatie?',
                'old_monitoring_removed' => 'Oude toebehoren verwijderd: Volledige batterijmeetsysteem?',
            ],
            'Diversen:' => [
                'damage' => 'Zijn er beschadigingen aangebracht? (deuren, gangen, trappen,…)',
                'packaging_removed' => 'Is al het verpakkings en transport materiaal verwijderd?',
                'room_cleaned' => 'Is het lokaal gereinigd?',
                'available' => 'Is de batterij beschikbaar voor laden en ontladen',
                'doc_maintenance' => 'Documentatie geleverd: Onderhouds instructies',
                'doc_installation' => 'Documentatie geleverd: Installatie instructies',
                'photos' => "Documentatie geleverd: Foto's voor en na de werken zijn genomen?",
                'doc_holder' => 'Documentatie geleverd: Is er een documenthouder geplaatst?',
                'measurements_available' => 'Zijn de meetresulaten van de batterijen voor de eerste vlotting, ter beschikking?',
            ],
            'Persoonlijke beschermingsmiddelen:' => [
                'face_shield' => 'Werd er een gezichtsbescherming geleverd?',
                'apron' => 'Werd er een zuurbestendige schort geleverd?',
                'gloves' => 'Werden er zuurbestendige handschoenen geleverd?',
                'eye_wash' => 'Werd er een barikos oogspoelfles geleverd?',
                'ppe_cabinet' => 'Zijn deze zaken opgeborgen in een kastje?',
            ],
            'Onderhoudsmateriaal:' => [
                'thermometer' => 'Is er een thermometer geleverd?',
                'hydrometer' => 'Is er een zuurdichtheidsmeter geleverd?',
                'measuring_cup' => 'Is er een maatbeker geleverd?',
                'funnel' => 'Is er een trechter geleverd?',
                'maintenance_rack' => 'Zijn deze zaken geplaatst op een rekje in de omgeving van de wasbak?',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(array_merge(...array_values(self::sections())));
    }

    /**
     * The visual inspection of the Excel "12V enersys" (12 V monoblocks in a cabinet): a shorter list,
     * with the same keys where the question is the same.
     *
     * @return array<string, array<string, string>>
     */
    public static function enersysSections(): array
    {
        return [
            'Contole van de cellen of monobloks' => [
                'cells_identical' => 'Zijn alle cellen identiek?',
                'cells_same_date' => 'Zijn de cellen van dezelfde productiedatum',
                'cells_labelled' => 'Zijn de cellen voorzien van een etiket?',
                'label_manufacturer' => 'Etiket: Fabrikant',
                'label_type' => 'Etiket: Type van de cel',
                'label_capacity' => 'Etiket: Capaciteit van de cel',
                'label_float_voltage' => 'Etiket: De vlotspanning van de cel',
                'label_production_date' => 'Etiket: De fabricatie datum (maand en jaar)',
                'cells_measurable' => 'Is het mogelijk om de individuele spanning van de cellen te meten?',
            ],
            'Controle van de batterij:' => [
                'flexible_connectors' => 'Werden er soepele intercelverbinders gebruikt?',
                'connectors_insulated' => 'Zijn de intercelverbindingen geïsoleerd (IP20)?',
                'polarity_marked' => 'Is de pos en negatieve aansluiting gemarkeerd?',
                'battery_numbered' => 'Heeft de batterij een nummer (1, 1A, 2,  …) gekregen',
                'cells_numbered' => 'Heeft elke cel een nummer gekregen (start vanaf positief)',
                'markings_readable' => 'Zijn alle markeringen goed leesbaar?',
                'cleaned' => 'Zijn de cellen, intercelverbinders, … gereinigd?',
            ],
            'De opstelling:' => [
                'stable' => 'Zijn de batterijen stabiel opgesteld?',
                'rack_fits' => 'Is het gestel/kast (in afmetingen) aangepast aan de batterij?',
                'cells_accessible' => 'Zijn alle cellen vlot toegankelijk?',
            ],
            'Enkel bij opstelling in kast:' => [
                'doors_open' => 'Zijn de deuren van de kasten volledig te openen? (≥90°)',
                'doors_direction' => 'Draaien ze open naar de goede kant (zie plan) ?',
                'door_battery_number' => 'Markering op de deur: Nummer van de batterij',
                'door_battery_symbol' => 'Markering op de deur: Symbool van een batterij',
                'door_danger_triangle' => 'Markering op de deur: Elektrische gevarendriehoek',
                'door_voltage' => 'Markering op de deur: Bedrijfsspanning',
            ],
            'Verwijdering oude batterijen' => [
                'old_removed' => 'Werden er oude batterijen verwijderd?',
                'old_cells_removed' => 'Zijn alle cellen verwijderd van het Proximus terrein?',
                'old_parts_removed' => 'Zijn alle onderdelen verwijderd?',
                'old_rack_removed' => 'Werd de oude stelling volledig verwijderd?',
                'old_place_clean' => 'Is de oude opstellingsplaats opgekuist?',
                'old_accessories_removed' => 'Zijn alle oude toebehoren verwijderd?',
                'old_docs_removed' => 'Documentatie?',
            ],
            'Diversen:' => [
                'damage' => 'Zijn er beschadigingen aangebracht? (deuren, gangen, trappen,…)',
                'packaging_removed' => 'Is al het verpakkings en transport materiaal verwijderd?',
                'room_cleaned' => 'Is het lokaal gereinigd?',
                'available' => 'Is de batterij beschikbaar voor laden en ontladen',
                'documentation_delivered' => 'Is de documentatie geleverd?',
                'photos' => "Foto's voor en na de werken zijn genomen?",
                'measurements_available' => 'Zijn de meetresulaten van de batterijen voor de eerste vlotting, ter beschikking?',
            ],
        ];
    }
}
