<?php

// Deutsche Validierungsmeldungen. Ohne diese Datei zeigt die App rohe Schlüssel wie "validation.max.string".

return [
    'accepted' => ':Attribute muss akzeptiert werden.',
    'array' => ':Attribute muss eine Liste sein.',
    'boolean' => ':Attribute muss ja oder nein sein.',
    'date' => ':Attribute ist kein gültiges Datum.',
    'email' => ':Attribute muss eine gültige E-Mail-Adresse sein.',
    'exists' => 'Die Auswahl für :attribute ist ungültig.',
    'extensions' => ':Attribute muss eine dieser Dateiendungen haben: :values.',
    'file' => ':Attribute muss eine Datei sein.',
    'image' => ':Attribute muss ein Bild sein.',
    'in' => 'Die Auswahl für :attribute ist ungültig.',
    'integer' => ':Attribute muss eine ganze Zahl sein.',
    'max' => [
        'array' => ':Attribute darf höchstens :max Einträge haben.',
        'file' => ':Attribute darf höchstens :max Kilobyte groß sein.',
        'numeric' => ':Attribute darf höchstens :max sein.',
        'string' => ':Attribute darf höchstens :max Zeichen haben.',
    ],
    'mimes' => ':Attribute muss vom Typ :values sein.',
    'min' => [
        'array' => ':Attribute braucht mindestens :min Einträge.',
        'file' => ':Attribute muss mindestens :min Kilobyte groß sein.',
        'numeric' => ':Attribute muss mindestens :min sein.',
        'string' => ':Attribute muss mindestens :min Zeichen haben.',
    ],
    'numeric' => ':Attribute muss eine Zahl sein.',
    'required' => ':Attribute fehlt.',
    'string' => ':Attribute muss Text sein.',
    'unique' => ':Attribute ist schon vergeben.',
    'uploaded' => ':Attribute konnte nicht hochgeladen werden.',
    'uuid' => ':Attribute muss eine gültige UUID sein.',

    'attributes' => [
        'name' => 'Bezeichnung',
        'brand' => 'Marke',
        'model' => 'Modell',
        'number' => 'Nummer',
        'year' => 'Jahr',
        'variant' => 'Variante',
        'background' => 'Hintergrund',
        'condition' => 'Zustand',
        'condition_notes' => 'Anmerkungen zum Zustand',
        'platform' => 'Plattform',
        'hint' => 'Hinweis',
        'photos' => 'Fotos',
        'photos.*' => 'Foto',
        'email' => 'E-Mail',
        'password' => 'Passwort',
    ],
];
