<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Support\ListingExport;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class BuildDownloadZip extends ListingJob
{
    protected function label(): string
    {
        return 'ZIP bauen';
    }

    public function handle(): void
    {
        $listing = Listing::with('photos')->find($this->listingId);
        if (! $listing || ! $listing->text) {
            return;
        }

        $disk = Storage::disk('local');
        $relative = $listing->directory().'/zip/inserat.zip';
        $absolute = $disk->path($relative);
        @mkdir(dirname($absolute), 0775, true);
        $tmp = $absolute.'.tmp';

        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ZIP konnte nicht angelegt werden.');
        }
        foreach (ListingExport::orderedPhotos($listing) as $i => $photo) {
            $zip->addFile($disk->path($photo->final_path), sprintf('%02d.jpg', $i + 1));
        }
        $zip->addFromString('inserat.txt', ListingExport::text($listing));
        $zip->close();
        rename($tmp, $absolute);

        $listing->update(['zip_path' => $relative, 'status' => 'ready']);
    }
}
