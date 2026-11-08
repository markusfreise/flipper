<?php

namespace App\Jobs;

use App\Models\ListingPhoto;
use App\Services\Images\ExifNormalizer;
use App\Services\Images\PhotoComposer;
use App\Services\ListingFlow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ComposeListingPhoto implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public string $photoId) {}

    public function handle(PhotoComposer $composer): void
    {
        $photo = ListingPhoto::with('listing')->find($this->photoId);
        if (! $photo || ! $photo->cutout_path) {
            return;
        }

        $final = $photo->listing->directory().sprintf('/final/%02d.jpg', $photo->position);
        $composer->compose($photo->absolute($photo->cutout_path), $photo->absolute($final));
        $photo->update(['final_path' => $final, 'status' => 'composed']);

        ListingFlow::maybeFinish($photo->listing_id);
    }

    public function failed(?Throwable $e): void
    {
        $photo = ListingPhoto::with('listing')->find($this->photoId);
        if ($photo) {
            RemoveBackground::useOriginal($photo, app(ExifNormalizer::class), 'cutout_failed');
        }
    }
}
