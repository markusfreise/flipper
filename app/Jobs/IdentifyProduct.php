<?php

namespace App\Jobs;

use App\Contracts\LlmClient;
use App\Models\Listing;
use App\Services\Claude\IdentifyPrompt;
use App\Services\Claude\Images;
use App\Services\Images\ExifNormalizer;
use App\Services\ListingFlow;

class IdentifyProduct extends ListingJob
{
    protected function label(): string
    {
        return 'Produkterkennung';
    }

    public function handle(LlmClient $llm, ExifNormalizer $normalizer): void
    {
        $listing = Listing::with('photos')->find($this->listingId);
        if (! $listing || $listing->photos->isEmpty()) {
            return;
        }

        $content = [...Images::blocks($listing->photos, $normalizer), ['type' => 'text', 'text' => IdentifyPrompt::instruction()]];
        $result = $llm->json(IdentifyPrompt::system(), $content, IdentifyPrompt::schema());

        $product = $result['data'];
        $product['confidence'] = max(0, min(1, (float) ($product['confidence'] ?? 0)));
        $product['source'] = 'ai';

        $listing->update(['product' => $product]);
        ListingFlow::identificationStepDone($listing->id);
    }
}
