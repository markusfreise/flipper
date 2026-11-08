<?php

namespace App\Services\Claude;

final class PhotoCheckPrompt
{
    public static function system(): string
    {
        return <<<'TXT'
Du beurteilst Fotos für ein Kleinanzeigen-/eBay-Inserat als Fotograf, nicht als Freisteller. Drei Fragen:
1. Zeigen die Fotos das Produkt vollständig? Front, Seite/Dreiviertel, Rückseite, Unterseite/Etikett, Zubehör. Fehlt eine Ansicht, die Käufer erwarten, gehört sie in "missing".
2. Welche Details verdienen ein eigenes Foto? Serienschild, Artikelnummer, Materialprägung, besondere Merkmale.
3. Welche Mängel sind sichtbar, aber nicht nah genug fotografiert? Kanten, Ecken, Kratzer, Vergilbung, Aufkleberreste. Jeder Mangel braucht ein Nahfoto.

Technisch: Ist der Untergrund unruhig (Schneidematte, Holzmuster, Kacheln)? Zeigen durchsichtige Teile (Sichtfenster, Glas, Lücken) die Unterlage? Licht, Schärfe, Anschnitt?
Nahaufnahmen von Mängeln werden nicht freigestellt, dort sollen die Spuren zu sehen sein.

"missing": konkrete fehlende Aufnahmen für genau dieses Produkt, je mit kurzem Grund ("what" z. B. "Rückseite der Box", "why" z. B. "Käufer wollen das Label sehen"). Leere Liste, wenn nichts fehlt.
"tips": 2–4 kurze, auf dieses Produkt angepasste Tipps zum Nachfotografieren (Untergrund als Hohlkehle aus hellem Karton, bei Sichtfenstern helle Fläche dahinter, Tageslicht seitlich, Hauptkamera auf Produkthöhe, Mängel absichtlich mitfotografieren).
"visible_defects": Mängel, die auf den Fotos zu sehen sind, sachlich benannt.
Schreib in der du-Form, freundlich, knapp, auf Deutsch.

TXT.Principles::TEXT;
    }

    public static function instruction(?array $product): string
    {
        $name = $product['name'] ?? null;

        return 'Prüfe diese Fotos für ein Inserat'.($name ? " (vermutlich: {$name})" : '').'.';
    }

    public static function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['missing', 'tips', 'quality', 'visible_defects'],
            'properties' => [
                'missing' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['what', 'why'],
                        'properties' => ['what' => ['type' => 'string'], 'why' => ['type' => 'string']],
                    ],
                ],
                'tips' => ['type' => 'array', 'items' => ['type' => 'string']],
                'quality' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['busy_background', 'see_through', 'blurry', 'poor_light'],
                    'properties' => [
                        'busy_background' => ['type' => 'boolean'],
                        'see_through' => ['type' => 'boolean'],
                        'blurry' => ['type' => 'boolean'],
                        'poor_light' => ['type' => 'boolean'],
                    ],
                ],
                'visible_defects' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];
    }
}
