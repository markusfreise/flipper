<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ListingResource;
use App\Jobs\CheckPhotos;
use App\Jobs\RemoveBackground;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Services\Images\ExifNormalizer;
use App\Services\ListingFlow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class PhotoController extends Controller
{
    public function store(Request $request, Listing $listing, ExifNormalizer $normalizer): ListingResource
    {
        Gate::authorize('update', $listing);
        $max = config('flipper.max_photos');
        $free = $max - $listing->photos()->count();

        $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:'.max(1, $free)],
            'photos.*' => ['required', 'file', 'max:'.config('flipper.max_photo_kb'), 'extensions:jpg,jpeg,png,heic,heif,webp'],
            'kinds' => ['array'],
            'kinds.*' => [Rule::in(['overview', 'detail'])],
        ], [
            'photos.max' => "Höchstens {$max} Fotos pro Inserat.",
            'photos.*.max' => 'Ein Foto ist größer als 15 MB.',
            'photos.*.extensions' => 'Nur JPEG, PNG oder HEIC.',
        ]);
        abort_if($free <= 0, 422, "Höchstens {$max} Fotos pro Inserat.");

        $disk = Storage::disk('local');
        $kinds = $request->input('kinds', []);
        $created = [];

        foreach ($request->file('photos') as $i => $file) {
            $position = (int) $listing->photos()->max('position') + 1;
            $dir = $listing->directory();
            $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            $upload = $file->storeAs("{$dir}/originals", sprintf('%02d-upload.%s', $position, $ext), 'local');
            $original = sprintf('%s/originals/%02d.jpg', $dir, $position);

            try {
                $size = $normalizer->normalize($disk->path($upload), $disk->path($original));
                $normalizer->resizedJpeg($disk->path($original), $disk->path(sprintf('%s/thumbs/%02d.jpg', $dir, $position)), 480);
            } catch (Throwable $e) {
                $disk->delete($upload);
                throw ValidationException::withMessages(['photos' => $e->getMessage() ?: 'Foto konnte nicht gelesen werden.']);
            }

            $created[] = $listing->photos()->create([
                'position' => $position,
                'kind' => $kinds[$i] ?? 'overview',
                'upload_path' => $upload,
                'original_path' => $original,
                'width' => $size['width'],
                'height' => $size['height'],
                'status' => 'uploaded',
            ]);
        }

        $this->ensureCover($listing);
        $listing->refresh();

        if ($listing->status === 'photos') {
            ListingFlow::startIdentification($listing);
        } elseif ($listing->status !== 'identifying') {
            // Nachladen: Erkennung bleibt, Foto-Check wird mit allen Fotos aktualisiert.
            CheckPhotos::dispatch($listing->id);
        }
        ListingFlow::processPhotos($created);

        return new ListingResource($listing->fresh('photos'));
    }

    public function update(Request $request, Listing $listing, ListingPhoto $photo): ListingResource
    {
        Gate::authorize('update', $listing);
        abort_unless($photo->listing_id === $listing->id, 404);

        $data = $request->validate([
            'kind' => [Rule::in(['overview', 'detail'])],
            'is_cover' => ['boolean'],
        ]);

        if (($data['is_cover'] ?? false) === true) {
            DB::transaction(function () use ($listing, $photo) {
                $listing->photos()->update(['is_cover' => false]);
                $photo->update(['is_cover' => true]);
            });
            if ($listing->status === 'ready') {
                ListingFlow::maybeFinish($listing->id);
            }
        }

        if (isset($data['kind']) && $data['kind'] !== $photo->kind) {
            $photo->update(['kind' => $data['kind'], 'final_path' => null, 'cutout_path' => null, 'status' => 'uploaded']);
            RemoveBackground::dispatch($photo->id);
        }

        return new ListingResource($listing->fresh('photos'));
    }

    public function destroy(Listing $listing, ListingPhoto $photo): ListingResource
    {
        Gate::authorize('update', $listing);
        abort_unless($photo->listing_id === $listing->id, 404);

        Storage::disk('local')->delete(array_filter([
            $photo->upload_path, $photo->original_path, $photo->cutout_path, $photo->final_path,
            sprintf('%s/thumbs/%02d.jpg', $listing->directory(), $photo->position),
        ]));
        $photo->delete();
        $this->ensureCover($listing);
        ListingFlow::maybeFinish($listing->id);

        return new ListingResource($listing->fresh('photos'));
    }

    public function final(Listing $listing, ListingPhoto $photo): BinaryFileResponse
    {
        return $this->serve($listing, $photo, $photo->final_path, sprintf('%02d.jpg', $photo->position));
    }

    public function thumb(Listing $listing, ListingPhoto $photo): BinaryFileResponse
    {
        return $this->serve($listing, $photo, sprintf('%s/thumbs/%02d.jpg', $listing->directory(), $photo->position));
    }

    private function serve(Listing $listing, ListingPhoto $photo, ?string $path, ?string $downloadName = null): BinaryFileResponse
    {
        Gate::authorize('view', $listing);
        abort_unless($photo->listing_id === $listing->id && $path && Storage::disk('local')->exists($path), 404);

        $response = response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
        if ($downloadName && request()->boolean('download')) {
            $response->setContentDisposition('attachment', $downloadName);
        }

        return $response;
    }

    private function ensureCover(Listing $listing): void
    {
        if ($listing->photos()->where('is_cover', true)->exists()) {
            return;
        }
        $first = $listing->photos()->where('kind', 'overview')->orderBy('position')->first()
            ?? $listing->photos()->orderBy('position')->first();
        $first?->update(['is_cover' => true]);
    }
}
