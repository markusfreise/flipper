<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\ListingPhoto;
use Illuminate\Support\Collection;

class ListingExport
{
    /** Fertige Bilder, Titelbild zuerst. @return Collection<int, ListingPhoto> */
    public static function orderedPhotos(Listing $listing): Collection
    {
        return $listing->photos
            ->filter(fn (ListingPhoto $p) => $p->final_path !== null)
            ->sortBy(fn (ListingPhoto $p) => [$p->is_cover ? 0 : 1, $p->position])
            ->values();
    }

    public static function euro(?float $v): string
    {
        return $v === null ? 'selbst festlegen' : number_format($v, 2, ',', '.').' €';
    }

    public static function text(Listing $listing): string
    {
        $t = $listing->text ?? [];
        $p = $listing->pricing ?? [];
        $platform = $listing->answers['platform'] ?? 'kleinanzeigen';

        $lines = ['TITEL', $t['title'] ?? '', '', 'BESCHREIBUNG', $t['body'] ?? '', '', 'PREIS'];
        if ($platform !== 'ebay') {
            $lines[] = 'Kleinanzeigen: '.self::euro($p['kleinanzeigen_eur'] ?? null).' VB';
        }
        if ($platform !== 'kleinanzeigen') {
            $lines[] = 'eBay Sofortkauf: '.self::euro($p['ebay_eur'] ?? null);
        }
        $lines[] = $p['rationale'] ?? '';
        $lines[] = '';
        $lines[] = 'VERSAND';
        if ($platform !== 'ebay') {
            $lines[] = 'Kleinanzeigen: '.($p['shipping']['kleinanzeigen_text'] ?? '');
        }
        if ($platform !== 'kleinanzeigen' && isset($p['shipping']['ebay'])) {
            $e = $p['shipping']['ebay'];
            $lines[] = 'eBay: '.$e['service'].($e['price_eur'] !== null ? ' '.self::euro($e['price_eur']) : '').($e['note'] ? ' ('.$e['note'].')' : '');
        }
        if ($platform !== 'kleinanzeigen') {
            $lines[] = '';
            $lines[] = 'EBAY-ARTIKELMERKMALE';
            foreach (self::ebayAttributes($listing) as $label => $value) {
                $lines[] = "{$label}: {$value}";
            }
        }
        $sources = $listing->research['sources'] ?? [];
        if ($sources) {
            $lines[] = '';
            $lines[] = 'QUELLEN';
            array_push($lines, ...$sources);
        }

        return implode("\n", $lines)."\n";
    }

    /** @return array<string, string> */
    public static function ebayAttributes(Listing $listing): array
    {
        $a = $listing->text['ebay_attributes'] ?? [];
        $labels = ['brand' => 'Marke', 'series' => 'Serie', 'number' => 'Nummer', 'ean' => 'EAN/UPC', 'material' => 'Material'];

        return collect($labels)
            ->mapWithKeys(fn ($label, $key) => [$label => $a[$key] ?? null])
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->all();
    }
}
