<?php

// Only the rules this application uses; anything else falls back to English.
return [
    'array' => 'Het veld :attribute moet een lijst zijn.',
    'confirmed' => 'De bevestiging van :attribute komt niet overeen.',
    'current_password' => 'Het wachtwoord is onjuist.',
    'email' => 'Het veld :attribute moet een geldig e-mailadres zijn.',
    'enum' => 'De gekozen :attribute is ongeldig.',
    'file' => 'Het veld :attribute moet een bestand zijn.',
    'in' => 'De gekozen :attribute is ongeldig.',
    'lowercase' => 'Het veld :attribute mag enkel kleine letters bevatten.',
    'max' => [
        'array' => 'Je kan maximaal :max :attribute toevoegen.',
        'file' => 'De :attribute mag niet groter zijn dan :max kilobytes.',
        'numeric' => 'Het veld :attribute mag niet groter zijn dan :max.',
        'string' => 'Het veld :attribute mag niet meer dan :max tekens bevatten.',
    ],
    'mimes' => 'De :attribute moet een bestand zijn van het type: :values.',
    'min' => [
        'string' => 'Het veld :attribute moet minstens :min tekens bevatten.',
    ],
    'password' => [
        'letters' => 'Het veld :attribute moet minstens één letter bevatten.',
        'mixed' => 'Het veld :attribute moet minstens één hoofdletter en één kleine letter bevatten.',
        'numbers' => 'Het veld :attribute moet minstens één cijfer bevatten.',
        'symbols' => 'Het veld :attribute moet minstens één symbool bevatten.',
        'uncompromised' => 'Dit :attribute komt voor in een datalek. Kies een ander :attribute.',
    ],
    'required' => 'Het veld :attribute is verplicht.',
    'required_with' => 'Het veld :attribute is verplicht als :values ingevuld is.',
    'string' => 'Het veld :attribute moet tekst zijn.',
    'unique' => 'Dit :attribute is al in gebruik.',
    'uploaded' => 'Het uploaden van :attribute is mislukt.',

    'attributes' => [],
];
