<?php

// Only the rules this application uses; anything else falls back to English.
return [
    'array' => 'Le champ :attribute doit être une liste.',
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'current_password' => 'Le mot de passe est incorrect.',
    'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
    'enum' => 'La valeur sélectionnée pour :attribute est invalide.',
    'file' => 'Le champ :attribute doit être un fichier.',
    'in' => 'La valeur sélectionnée pour :attribute est invalide.',
    'lowercase' => 'Le champ :attribute ne peut contenir que des minuscules.',
    'max' => [
        'array' => 'Vous pouvez ajouter au maximum :max :attribute.',
        'file' => 'La :attribute ne peut pas dépasser :max kilo-octets.',
        'numeric' => 'Le champ :attribute ne peut pas être supérieur à :max.',
        'string' => 'Le champ :attribute ne peut pas dépasser :max caractères.',
    ],
    'mimes' => 'La :attribute doit être un fichier de type : :values.',
    'min' => [
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'password' => [
        'letters' => 'Le champ :attribute doit contenir au moins une lettre.',
        'mixed' => 'Le champ :attribute doit contenir au moins une majuscule et une minuscule.',
        'numbers' => 'Le champ :attribute doit contenir au moins un chiffre.',
        'symbols' => 'Le champ :attribute doit contenir au moins un symbole.',
        'uncompromised' => 'Ce :attribute apparaît dans une fuite de données. Choisissez-en un autre.',
    ],
    'required' => 'Le champ :attribute est obligatoire.',
    'required_with' => 'Le champ :attribute est obligatoire lorsque :values est rempli.',
    'string' => 'Le champ :attribute doit être du texte.',
    'unique' => 'Ce :attribute est déjà utilisé.',
    'uploaded' => 'Le téléversement de :attribute a échoué.',

    'attributes' => [],
];
