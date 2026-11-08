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
    /** Gleiche Grenzen wie die Validierung in ListingController::product(). */
    public const MAX_LENGTHS = ['name' => 200, 'brand' => 120, 'model' => 120, 'number' => 60, 'variant' => 120];

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
        $listing->update(['product' => self::clamp($product)]);
        ListingFlow::identificationStepDone($listing->id);
    }

    /** Sicherheitsnetz: Die KI-Werte müssen durch PUT /product (Schritt 2, "Stimmt so") passen. */
    public static function clamp(array $product): array
    {
        foreach (self::MAX_LENGTHS as $field => $max) {
            if (is_string($product[$field] ?? null)) {
                $product[$field] = mb_strimwidth(trim($product[$field]), 0, $max, '…');
            }
        }

        return $product;
    }
}
