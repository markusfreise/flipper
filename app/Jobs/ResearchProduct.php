<?php

namespace App\Jobs;

use App\Contracts\LlmClient;
use App\Models\Listing;
use App\Services\Claude\ResearchPrompt;
use App\Services\ListingFlow;

class ResearchProduct extends ListingJob
{
    protected function label(): string
    {
        return 'Recherche';
    }

    public function handle(LlmClient $llm): void
    {
        $listing = Listing::find($this->listingId);
        if (! $listing || ! $listing->product) {
            return;
        }

        $result = $llm->json(
            ResearchPrompt::system(),
            [['type' => 'text', 'text' => ResearchPrompt::instruction($listing->product)]],
            ResearchPrompt::schema(),
            webSearches: max(1, (int) config('flipper.anthropic.max_web_searches')),
        );

        $research = $result['data'];
        $research['features'] = array_values(array_filter($research['features'] ?? [], fn ($f) => ! empty($f['text'])));
        $research['sold_prices'] = array_values(array_filter($research['sold_prices'] ?? [], fn ($p) => is_numeric($p['price_eur'] ?? null)));
        $research['sources'] = array_values(array_unique(array_filter([
            ...array_column($research['features'], 'source_url'),
            ...array_column($research['sold_prices'], 'source_url'),
            ...$result['sources'],
        ])));

        $listing->update(['research' => $research]);
        ListingFlow::researchDone($listing->id);
    }
}
