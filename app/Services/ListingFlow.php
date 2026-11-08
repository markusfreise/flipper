<?php

namespace App\Services;

use App\Jobs\BuildDownloadZip;
use App\Jobs\CheckPhotos;
use App\Jobs\IdentifyProduct;
use App\Jobs\RemoveBackground;
use App\Jobs\ResearchProduct;
use App\Jobs\WriteListingText;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Support\Ntfy;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Statusmaschine und Job-Orchestrierung (PRD §8):
 * photos → identifying → identified → researching → questions → writing → ready, failed von überall.
 */
class ListingFlow
{
    /** Führt $fn mit gesperrter, frischer Listing-Zeile aus. */
    public static function locked(string $listingId, Closure $fn): mixed
    {
        return DB::transaction(function () use ($listingId, $fn) {
            $listing = Listing::query()->lockForUpdate()->find($listingId);

            return $listing ? $fn($listing) : null;
        });
    }

    /** Nach dem ersten Upload: Erkennung, Foto-Check und Freisteller anstoßen. */
    public static function startIdentification(Listing $listing): void
    {
        $listing->update(['status' => 'identifying', 'last_error' => null]);
        IdentifyProduct::dispatch($listing->id);
        CheckPhotos::dispatch($listing->id);
    }

    /** @param iterable<ListingPhoto> $photos */
    public static function processPhotos(iterable $photos): void
    {
        foreach ($photos as $photo) {
            RemoveBackground::dispatch($photo->id);
        }
    }

    /** Erkennung und Foto-Check fertig → Schritt 2. */
    public static function identificationStepDone(string $listingId): void
    {
        self::locked($listingId, function (Listing $l) {
            if ($l->status === 'identifying' && $l->product !== null && $l->photo_check !== null) {
                $l->update(['status' => 'identified']);
            }
        });
    }

    public static function startResearch(Listing $listing): void
    {
        $listing->update(['status' => 'researching', 'last_error' => null]);
        ResearchProduct::dispatch($listing->id);
    }

    /** Recherche fertig: Text schreiben, wenn die Antworten schon da sind, sonst auf die Fragen warten. */
    public static function researchDone(string $listingId): void
    {
        self::locked($listingId, function (Listing $l) {
            if ($l->answers !== null && in_array($l->status, ['writing', 'researching', 'questions'], true)) {
                $l->update(['status' => 'writing']);
                WriteListingText::dispatch($l->id);
            } elseif ($l->status === 'researching') {
                $l->update(['status' => 'questions']);
            }
        });
    }

    public static function answersGiven(Listing $listing): void
    {
        $listing->update(['status' => 'writing', 'last_error' => null]);
        if ($listing->research !== null) {
            WriteListingText::dispatch($listing->id);
        }
        // sonst startet researchDone() den Text, sobald die Recherche fertig ist
    }

    /** Text da und alle Bilder fertig → ZIP bauen (danach ready). Auch nach Nachladen im Ergebnis. */
    public static function maybeFinish(string $listingId): void
    {
        $dispatch = self::locked($listingId, function (Listing $l) {
            if (! in_array($l->status, ['writing', 'ready'], true) || $l->text === null) {
                return false;
            }

            return $l->photos()->whereNull('final_path')->doesntExist();
        });

        if ($dispatch) {
            BuildDownloadZip::dispatch($listingId);
        }
    }

    public static function fail(string $listingId, string $message): void
    {
        $listing = self::locked($listingId, function (Listing $l) use ($message) {
            $l->update([
                'status_before_failure' => $l->status === 'failed' ? $l->status_before_failure : $l->status,
                'status' => 'failed',
                'last_error' => $message,
            ]);

            return $l;
        });

        if ($listing) {
            Ntfy::send('Flohmarkt: Job fehlgeschlagen', "Inserat {$listingId}: {$message}", 'high');
        }
    }

    /** "Nochmal versuchen": zurück in den vorherigen Zustand und den passenden Schritt neu anstoßen. */
    public static function retry(Listing $listing): void
    {
        $previous = $listing->status_before_failure ?? 'photos';
        $listing->update(['status' => $previous, 'status_before_failure' => null, 'last_error' => null]);

        match ($previous) {
            'identifying' => self::retryIdentification($listing),
            'researching', 'questions' => self::startResearch($listing->fresh()),
            'writing', 'ready' => $listing->research === null
                ? ResearchProduct::dispatch($listing->id)
                : WriteListingText::dispatch($listing->id),
            default => null,
        };
    }

    private static function retryIdentification(Listing $listing): void
    {
        if ($listing->product === null) {
            IdentifyProduct::dispatch($listing->id);
        }
        if ($listing->photo_check === null) {
            CheckPhotos::dispatch($listing->id);
        }
        self::identificationStepDone($listing->id);
    }
}
