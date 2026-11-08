<?php

namespace App\Services\Claude;

final class ResearchPrompt
{
    public static function system(): string
    {
        return <<<'TXT'
Du recherchierst für ein privates Verkaufsinserat (Kleinanzeigen.de / eBay, Deutschland) im Web.

1. Features: 3–5 Punkte, die Käufer wissen wollen (Jahr, Maße, Serie, Besonderheiten). Herstellerseite oder Händlerseiten bevorzugen. Jeder Punkt mit der URL, auf der er steht.
2. Preis: Zuerst VERKAUFTE Preise, keine Angebotspreise. Quellen: eBay "verkauft", pricecharting.com, funkypriceguide.com (Sammlerstücke), Kleinanzeigen-Suche. US-Preise grob 1:1 in Euro übertragen und in "note" als "US-Preis, Orientierung" kennzeichnen. price_median_eur = Median der gefundenen Verkaufspreise.
3. Versand: Maße (cm) und Gewicht (g) des Artikels inkl. Originalverpackung aus Produktdaten, sonst begründet schätzen und in "notes" sagen, dass es geschätzt ist.

Nur Fakten mit Quelle. Was du nicht findest, lässt du leer oder setzt null. Keine Zahlen erfinden.
Alle Texte auf Deutsch.

TXT.Principles::TEXT;
    }

    public static function instruction(array $product): string
    {
        $facts = array_filter([
            'Produkt' => $product['name'] ?? null,
            'Hersteller' => $product['brand'] ?? null,
            'Modell' => $product['model'] ?? null,
            'Nummer' => $product['number'] ?? null,
            'Jahr' => $product['year'] ?? null,
            'Variante' => $product['variant'] ?? null,
            'EAN' => $product['identifiers']['ean'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $lines = collect($facts)->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n");

        return "Recherchiere zu diesem Gegenstand:\n{$lines}";
    }

    public static function schema(): array
    {
        $num = ['type' => ['number', 'null']];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['features', 'sold_prices', 'price_median_eur', 'dimensions_cm', 'weight_g', 'notes'],
            'properties' => [
                'features' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['text', 'source_url'],
                        'properties' => ['text' => ['type' => 'string'], 'source_url' => ['type' => ['string', 'null']]],
                    ],
                ],
                'sold_prices' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['price_eur', 'date', 'source_url', 'note'],
                        'properties' => [
                            'price_eur' => ['type' => 'number'],
                            'date' => ['type' => ['string', 'null']],
                            'source_url' => ['type' => ['string', 'null']],
                            'note' => ['type' => ['string', 'null']],
                        ],
                    ],
                ],
                'price_median_eur' => $num,
                'dimensions_cm' => [
                    'type' => ['object', 'null'],
                    'additionalProperties' => false,
                    'required' => ['length', 'width', 'height'],
                    'properties' => ['length' => $num, 'width' => $num, 'height' => $num],
                ],
                'weight_g' => $num,
                'notes' => ['type' => ['string', 'null']],
            ],
        ];
    }
}
