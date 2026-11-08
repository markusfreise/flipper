<?php

namespace App\Jobs;

use App\Models\ListingPhoto;
use App\Services\Images\ExifNormalizer;
use App\Services\ListingFlow;
use App\Services\Rembg\RembgClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Freistellen per rembg. Nahaufnahmen (kind=detail) werden nicht freigestellt. Fehler brechen den Flow nicht ab. */
class RemoveBackground implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    /** Eigene Queue: Der Worker nimmt "default" (KI-Schritte) immer zuerst, Bilder laufen dazwischen. */
    public const QUEUE = 'images';

    public function __construct(public string $photoId)
    {
        $this->onQueue(self::QUEUE);
    }

    public function handle(RembgClient $rembg, ExifNormalizer $normalizer): void
    {
        $photo = ListingPhoto::with('listing')->find($this->photoId);
        if (! $photo) {
            return;
        }

        if ($photo->isDetail()) {
            self::useOriginal($photo, $normalizer, 'composed');

            return;
        }

        $photo->update(['status' => 'cutting']);

        try {
            $png = $rembg->removeBackground($photo->absolute($photo->original_path));
            $path = $photo->listing->directory().sprintf('/cutouts/%02d.png', $photo->position);
            Storage::disk('local')->put($path, $png);
            $photo->update(['cutout_path' => $path, 'status' => 'cut']);
        } catch (Throwable $e) {
            Log::warning("Freistellen fehlgeschlagen ({$photo->id}): ".$e->getMessage());
            self::useOriginal($photo, $normalizer, 'cutout_failed');

            return;
        }

        ComposeListingPhoto::dispatch($photo->id);
    }

    /** Original (nur verkleinert, sonst unverändert) als fertiges Bild verwenden. */
    public static function useOriginal(ListingPhoto $photo, ExifNormalizer $normalizer, string $status): void
    {
        $final = $photo->listing->directory().sprintf('/final/%02d.jpg', $photo->position);
        $normalizer->resizedJpeg($photo->absolute($photo->original_path), $photo->absolute($final));
        $photo->update(['final_path' => $final, 'status' => $status]);
        ListingFlow::maybeFinish($photo->listing_id);
    }
}
