<?php

namespace App\Jobs;

use App\Contracts\LlmClient;
use App\Models\Listing;
use App\Services\Claude\ListingTextPrompt;
use App\Services\ListingFlow;
use App\Services\Pricing\PriceAdvisor;
use App\Services\Pricing\ShippingAdvisor;

class WriteListingText extends ListingJob
{
    public const TITLE_MAX = 65;

    public function __construct(string $listingId, public ?string $hint = null)
    {
        parent::__construct($listingId);
    }

    protected function label(): string
    {
        return 'Text schreiben';
    }

    public function handle(LlmClient $llm, PriceAdvisor $prices, ShippingAdvisor $shipping): void
    {
        $listing = Listing::with('photos')->find($this->listingId);
        if (! $listing || ! $listing->research || ! $listing->answers) {
            return;
        }

        $price = $prices->advise($listing->research, $listing->answers['condition'] ?? []);
        $listing->pricing = [...$price, 'shipping' => $shipping->advise($listing->research, $price['kleinanzeigen_eur'])];
        $listing->save();

        $result = $llm->json(
            ListingTextPrompt::system(),
            [['type' => 'text', 'text' => ListingTextPrompt::instruction($listing, config('flohmarkt.pickup_location'), $this->hint)]],
            ListingTextPrompt::schema(),
        )['data'];

        $listing->update(['text' => [
            'title' => self::cleanTitle((string) ($result['title'] ?? '')),
            'body' => self::cleanBody((string) ($result['body'] ?? '')),
            'ebay_attributes' => $result['ebay_attributes'] ?? [],
            'invented' => array_values(array_filter($result['invented'] ?? [])),
            'revision' => ($listing->text['revision'] ?? 0) + 1,
        ]]);

        ListingFlow::maybeFinish($listing->id);
    }

    /** Keine Gedankenstriche (Grundsatz). Zahlenbereiche werden zu "10-15". */
    public static function cleanBody(string $body): string
    {
        $body = str_replace(["\r\n", '**', '__'], ["\n", '', ''], $body);
        $body = preg_replace('/(\d)\s*[–—]\s*(\d)/u', '$1-$2', $body);
        $body = preg_replace('/^\s*[–—•*]\s+/mu', '- ', $body);
        $body = preg_replace('/\s+[–—]\s+/u', ', ', $body);
        $body = str_replace(['–', '—'], '-', $body);
        $body = preg_replace('/^#+\s*/m', '', $body);
        $body = str_replace(['Zustand, ehrlich:', 'Zustand (ehrlich):'], 'Zustand:', $body);

        return trim(preg_replace("/\n{3,}/", "\n\n", $body));
    }

    public static function cleanTitle(string $title): string
    {
        $title = trim(preg_replace('/\s+[–—]\s+/u', ' ', str_replace(["\n", '*'], [' ', ''], $title)));
        if (mb_strlen($title) <= self::TITLE_MAX) {
            return $title;
        }
        $cut = mb_substr($title, 0, self::TITLE_MAX);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space > 30 ? mb_substr($cut, 0, $space) : $cut, ' ,;:-');
    }
}
