<?php

namespace App\Services\Claude;

final class IdentifyPrompt
{
    public static function system(): string
    {
        return <<<'TXT'
Du hilfst Privatleuten, gebrauchte Dinge auf Kleinanzeigen.de und eBay zu verkaufen. Du siehst Fotos eines Gegenstands und bestimmst ihn so genau wie möglich.

Bestimme: Bezeichnung (so, wie Käufer danach suchen), Hersteller/Marke, Modell, Artikel- oder Seriennummer, Variante (bei Sammlerstücken: Exclusive, Chase, Standardausgabe), Erscheinungsjahr, sichtbare Kennzeichen (Aufdrucke, EAN/UPC, MPN).
Bezeichnung und Variante sind kurz, wie eine Suchanfrage: nur das Produkt, kein Zubehör, kein Zustand, keine Verpackung (also "DJI Osmo 360", nicht "DJI Osmo 360 mit Hülle und OVP"). Zubehör, Lieferumfang und Zustand gehören in notes.
Lies nur ab, was auf den Fotos steht oder sicher daraus folgt. Was du nicht sicher weißt, setzt du auf null. confidence ist deine Sicherheit zwischen 0 und 1 für die Gesamtbestimmung.
Antworte auf Deutsch.

TXT.Principles::TEXT;
    }

    public static function instruction(): string
    {
        return 'Was ist das für ein Gegenstand? Bestimme ihn anhand der Fotos.';
    }

    public static function schema(): array
    {
        $str = ['type' => ['string', 'null']];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['name', 'brand', 'model', 'number', 'year', 'variant', 'confidence', 'identifiers', 'notes'],
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Produktbezeichnung ohne Zubehör und Zustand, höchstens etwa 80 Zeichen, z. B. "Funko Pop! Harry Potter #14 Luna Lovegood"'],
                'brand' => $str,
                'model' => $str,
                'number' => $str,
                'year' => ['type' => ['integer', 'null']],
                'variant' => ['type' => ['string', 'null'], 'description' => 'Kurz, z. B. "Standardausgabe", "Chase", "Adventure Combo", "schwarz"; kein Zubehör'],
                'confidence' => ['type' => 'number'],
                'identifiers' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['ean', 'mpn'],
                    'properties' => ['ean' => $str, 'mpn' => $str],
                ],
                'notes' => ['type' => ['string', 'null'], 'description' => 'Was unsicher ist und warum'],
            ],
        ];
    }
}
