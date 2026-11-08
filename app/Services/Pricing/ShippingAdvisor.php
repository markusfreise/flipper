<?php

namespace App\Services\Pricing;

/**
 * Versandvorschlag. Kleinanzeigen: eigene Versandarten, der Käufer wählt und zahlt,
 * deshalb ein konstanter Text. eBay: kleinste passende DHL-Stufe aus der Config.
 */
class ShippingAdvisor
{
    /**
     * @param  array<string, mixed>  $research
     * @return array{kleinanzeigen_text: string, ebay: array{service: string, price_eur: ?float, note: ?string}}
     */
    public function advise(array $research, ?float $itemValueEur = null): array
    {
        $cfg = config('flohmarkt.shipping');
        $dims = $research['dimensions_cm'] ?? null;
        $weight = is_numeric($research['weight_g'] ?? null) ? (float) $research['weight_g'] : null;

        $sizes = is_array($dims) ? array_filter([$dims['length'] ?? null, $dims['width'] ?? null, $dims['height'] ?? null], 'is_numeric') : [];

        if (count($sizes) < 3) {
            return [
                'kleinanzeigen_text' => $cfg['kleinanzeigen_text'],
                'ebay' => ['service' => 'DHL Päckchen M', 'price_eur' => 5.19, 'note' => 'Maße unbekannt, bitte nachmessen.'],
            ];
        }

        $sizes = array_map(fn ($v) => (float) $v + $cfg['packaging_cm'], $sizes);
        rsort($sizes);
        $grams = ($weight ?? 0) + $cfg['packaging_g'];

        $tiers = $cfg['dhl'];
        // Wertvolles versichert verschicken: ab 50 € nur Paket.
        if ($itemValueEur !== null && $itemValueEur >= 50) {
            $tiers = array_values(array_filter($tiers, fn ($t) => str_contains($t['service'], 'Paket')));
        }

        foreach ($tiers as $tier) {
            $max = $tier['max_cm'];
            rsort($max);
            if ($sizes[0] <= $max[0] && $sizes[1] <= $max[1] && $sizes[2] <= $max[2] && $grams <= $tier['max_g']) {
                return [
                    'kleinanzeigen_text' => $cfg['kleinanzeigen_text'],
                    'ebay' => [
                        'service' => $tier['service'],
                        'price_eur' => $tier['price_eur'],
                        'note' => $weight === null ? 'Gewicht geschätzt.' : null,
                    ],
                ];
            }
        }

        return [
            'kleinanzeigen_text' => $cfg['kleinanzeigen_text'],
            'ebay' => ['service' => 'DHL Paket (nach Gewicht)', 'price_eur' => null, 'note' => 'Größer als 60 × 30 × 15 cm oder über 2 kg: Preis auf dhl.de prüfen.'],
        ];
    }
}
