<?php

namespace App\Jobs;

use App\Contracts\LlmClient;
use App\Models\Listing;
use App\Services\Claude\Images;
use App\Services\Claude\PhotoCheckPrompt;
use App\Services\Images\ExifNormalizer;
use App\Services\ListingFlow;

class CheckPhotos extends ListingJob
{
    protected function label(): string
    {
        return 'Foto-Check';
    }

    public function handle(LlmClient $llm, ExifNormalizer $normalizer): void
    {
        $listing = Listing::with('photos')->find($this->listingId);
        if (! $listing || $listing->photos->isEmpty()) {
            return;
        }

        $content = [...Images::blocks($listing->photos, $normalizer), ['type' => 'text', 'text' => PhotoCheckPrompt::instruction($listing->product)]];
        $check = $llm->json(PhotoCheckPrompt::system(), $content, PhotoCheckPrompt::schema())['data'];

        // Auflösung prüfen wir selbst: unter 2000 px an der langen Kante sind es keine Originale.
        $minEdge = config('flipper.min_original_edge_px');
        $lowRes = $listing->photos->contains(fn ($p) => max($p->width ?? 0, $p->height ?? 0) < $minEdge);
        $check['quality'] = ['low_res' => $lowRes, ...($check['quality'] ?? [])];
        $check['missing'] ??= [];
        $check['tips'] ??= [];
        if ($lowRes) {
            array_unshift($check['missing'], [
                'what' => 'Originale in voller Auflösung',
                'why' => 'Mindestens ein Foto ist kleiner als '.$minEdge.' px. iPhone: in der Fotos-App "Unverändertes Original" exportieren oder per AirDrop schicken.',
            ]);
        }

        $listing->update(['photo_check' => $check]);
        ListingFlow::identificationStepDone($listing->id);
    }
}
