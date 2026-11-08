<?php

namespace App\Services\Claude;

use App\Models\Listing;

final class ListingTextPrompt
{
    public static function system(): string
    {
        return <<<'TXT'
Du schreibst Verkaufsinserate für Privatleute auf Kleinanzeigen.de und eBay. Stimme: neutral, freundlich, wie ein ordentlicher Privatverkäufer, du-Form. Ein Inserat ist Gebrauchstext: nüchtern, höchstens ein einziges Augenzwinkern.

Formatregeln:
- Fließtext, keine Emojis, kein Markdown (keine Sternchen, keine Rauten), 120–200 Wörter.
- Keine Gedankenstriche (– oder —). Nutze Punkt, Komma oder Doppelpunkt.
- Keine Preise im Text. Kein Werbesprech ("Must-have", "Highlight", "einmalige Gelegenheit" usw.).
- Titel max. 65 Zeichen: Marke, Produkt, Nummer/Modell, Zustandskürzel (OVP, neuwertig, gebraucht, defekt …). Suchbegriffe, die Käufer eintippen, vor Adjektiven.

Aufbau des Textes (Reihenfolge, Überschriften genau so, jeweils in eigener Zeile mit Doppelpunkt):
1. Einstieg (ohne Überschrift, 2–4 Sätze): baut auf den Worten der Verkäuferin oder des Verkäufers auf. Ein Satz zum "Warum hatte ich das", einer zum "Warum geht es weg". Ein konkretes Detail, kein Pathos.
2. "Was du bekommst:" 3–5 Zeilen, jede beginnt mit "- ", Features aus der Recherche.
3. "Zustand:" (genau so, nicht "Zustand, ehrlich"). Alles Sichtbare benennen plus die Angaben. "siehe Fotos" nur, wenn es Nahaufnahmen gibt.
4. "Abholung oder Versand:" Ort, Versandweg wie vorgegeben, und der Satz "Privatverkauf, keine Garantie oder Rücknahme."

Kleine erzählerische Ausschmückungen, die über die Angaben hinausgehen, sind im Einstieg erlaubt. Jede davon listest du wörtlich in "invented" auf, damit sie gestrichen werden kann. Alles andere muss belegt sein.
ebay_attributes: nur Werte, die belegt sind, sonst null.

TXT.Principles::TEXT;
    }

    public static function instruction(Listing $listing, string $pickupLocation, ?string $hint = null): string
    {
        $product = $listing->product ?? [];
        $research = $listing->research ?? [];
        $answers = $listing->answers ?? [];
        $pricing = $listing->pricing ?? [];
        $platform = $answers['platform'] ?? 'kleinanzeigen';
        $v = fn ($x) => ($x === null || $x === '') ? 'unbekannt' : (string) $x;

        $features = collect($research['features'] ?? [])->map(fn ($f) => '- '.$f['text'])->implode("\n") ?: '(keine recherchiert)';
        $defects = collect($listing->photo_check['visible_defects'] ?? [])->map(fn ($d) => '- '.$d)->implode("\n") ?: '(keine erkannt)';
        $condition = implode(', ', $answers['condition'] ?? []) ?: '(keine Angabe)';
        $hasDetailPhotos = $listing->photos->contains(fn ($p) => $p->isDetail());

        $pickup = ($answers['pickup'] ?? true) ? "Abholung in {$pickupLocation} möglich." : 'Keine Abholung, nur Versand.';
        $shipping = $platform === 'ebay'
            ? 'Versand: '.($pricing['shipping']['ebay']['service'] ?? 'DHL').(isset($pricing['shipping']['ebay']['price_eur']) ? ' für '.number_format($pricing['shipping']['ebay']['price_eur'], 2, ',', '').' €' : '').'.'
            : 'Versand: Formulierung "oder du lässt es dir '.config('flipper.shipping.kleinanzeigen_text').' schicken" (keinen eigenen DHL-Preis nennen).';

        $platformLabel = ['kleinanzeigen' => 'Kleinanzeigen.de', 'ebay' => 'eBay', 'both' => 'Kleinanzeigen.de (zuerst) und eBay'][$platform] ?? 'Kleinanzeigen.de';

        $text = <<<TXT
Kanal: {$platformLabel}

Produkt: {$v($product['name'] ?? null)}
Hersteller: {$v($product['brand'] ?? null)}
Modell/Nummer: {$v(trim(($product['model'] ?? '').' '.($product['number'] ?? '')))}
Jahr: {$v($product['year'] ?? null)}
Variante: {$v($product['variant'] ?? null)}
EAN: {$v($product['identifiers']['ean'] ?? null)}

Features aus der Recherche:
{$features}

Auf den Fotos sichtbare Mängel:
{$defects}
Nahaufnahmen von Mängeln vorhanden: {$v($hasDetailPhotos ? 'ja' : 'nein')}

Angaben der Verkäuferin/des Verkäufers (wörtlich):
Hintergrund: {$v($answers['background'] ?? null)}
Zustand: {$condition}
Sonst noch: {$v($answers['condition_notes'] ?? null)}

Abholung/Versand:
{$pickup}
{$shipping}
TXT;

        if ($listing->text && $hint) {
            $text .= "\n\nBisheriger Text:\nTitel: {$listing->text['title']}\n\n{$listing->text['body']}\n\nÜberarbeite ihn nach diesem Hinweis: {$hint}";
        }

        return $text;
    }

    public static function schema(): array
    {
        $str = ['type' => ['string', 'null']];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['title', 'body', 'ebay_attributes', 'invented'],
            'properties' => [
                'title' => ['type' => 'string'],
                'body' => ['type' => 'string'],
                'ebay_attributes' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['brand', 'series', 'number', 'ean', 'material'],
                    'properties' => ['brand' => $str, 'series' => $str, 'number' => $str, 'ean' => $str, 'material' => $str],
                ],
                'invented' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];
    }
}
