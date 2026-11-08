<?php

namespace App\Services\Pricing;

/**
 * Preisregel aus der Skill /verkaufen:
 * Kleinanzeigen = Median verkaufter Stücke, auf glatten Betrag gerundet, VB.
 * eBay Sofortkauf ≈ 20 % darüber (endet auf ,90). Zustand unter Durchschnitt: 25 % Abschlag.
 */
class PriceAdvisor
{
    public const DEDUCTION_CONDITIONS = ['Gebrauchsspuren', 'Zubehör fehlt'];

    public const DEDUCTION = 0.25;

    public const EBAY_MARKUP = 1.20;

    /**
     * @param  array<string, mixed>  $research
     * @param  list<string>  $condition
     * @return array{kleinanzeigen_eur: ?float, ebay_eur: ?float, median_eur: ?float, rationale: string, sources: list<string>}
     */
    public function advise(array $research, array $condition = []): array
    {
        $sold = collect($research['sold_prices'] ?? [])
            ->filter(fn ($p) => is_numeric($p['price_eur'] ?? null) && $p['price_eur'] > 0);

        $median = $sold->isNotEmpty()
            ? self::median($sold->pluck('price_eur')->map(fn ($v) => (float) $v)->all())
            : (is_numeric($research['price_median_eur'] ?? null) ? (float) $research['price_median_eur'] : null);

        $sources = $sold->pluck('source_url')->filter()->unique()->values()->all();

        if ($median === null) {
            return [
                'kleinanzeigen_eur' => null,
                'ebay_eur' => null,
                'median_eur' => null,
                'rationale' => 'Keine verkauften Vergleichsstücke gefunden. Preis bitte selbst festlegen, z. B. nach einer Kleinanzeigen-Suche zum Produktnamen.',
                'sources' => [],
            ];
        }

        $base = $median;
        $deducted = array_values(array_intersect($condition, self::DEDUCTION_CONDITIONS));
        if ($deducted) {
            $base *= (1 - self::DEDUCTION);
        }

        $ka = self::roundEven($base);
        $ebay = self::ebay($ka);

        $parts = [sprintf(
            'Median von %d verkauften %s: %s €.',
            $sold->count() ?: 1,
            ($sold->count() === 1) ? 'Stück' : 'Stücken',
            self::fmt($median),
        )];
        if ($sold->contains(fn ($p) => str_contains(mb_strtolower((string) ($p['note'] ?? '')), 'us'))) {
            $parts[] = 'US-Preise grob 1:1 in Euro übertragen, nur als Orientierung.';
        }
        if ($deducted) {
            $parts[] = sprintf('25 %% Abschlag wegen Zustand (%s).', implode(', ', $deducted));
        }
        $parts[] = 'Kleinanzeigen: auf glatten Betrag gerundet, VB. eBay Sofortkauf etwa 20 % darüber (Gebühren, Versandrisiko).';

        return [
            'kleinanzeigen_eur' => $ka,
            'ebay_eur' => $ebay,
            'median_eur' => round($median, 2),
            'rationale' => implode(' ', $parts),
            'sources' => $sources,
        ];
    }

    /** @param list<float> $values */
    public static function median(array $values): float
    {
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return $n % 2 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }

    /** Glatter Betrag: bis 20 € auf ganze Euro, bis 100 € auf 5 €, darüber auf 10 €. Mindestens 1 €. */
    public static function roundEven(float $value): float
    {
        $step = match (true) {
            $value < 20 => 1,
            $value < 100 => 5,
            default => 10,
        };

        return (float) max(1, round($value / $step) * $step);
    }

    /** eBay: +20 %, auf den nächsten vollen Euro aufgerundet, minus 10 Cent (14,40 → 14,90). */
    public static function ebay(float $kleinanzeigen): float
    {
        return round(ceil($kleinanzeigen * self::EBAY_MARKUP) - 0.10, 2);
    }

    private static function fmt(float $v): string
    {
        return number_format($v, 2, ',', '.');
    }
}
