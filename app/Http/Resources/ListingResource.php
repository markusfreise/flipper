<?php

namespace App\Http\Resources;

use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Support\ListingExport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Listing */
class ListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $base = "/files/listings/{$this->id}";
        $photos = $this->relationLoaded('photos') ? $this->photos : collect();
        $cover = $photos->firstWhere('is_cover', true) ?? $photos->first();

        return [
            'id' => $this->id,
            'status' => $this->status,
            'status_before_failure' => $this->status_before_failure,
            'product' => $this->product,
            'photo_check' => $this->photo_check,
            'research' => $this->research,
            'answers' => $this->answers,
            'pricing' => $this->pricing,
            'text' => $this->text,
            'ebay_attributes' => $this->text ? ListingExport::ebayAttributes($this->resource) : [],
            'text_revisions' => $this->text_revisions,
            'revisions_left' => max(0, config('flohmarkt.max_text_revisions') - $this->text_revisions),
            'last_error' => $this->last_error,
            'pickup_location' => config('flohmarkt.pickup_location'),
            'zip_url' => $this->zip_path ? "{$base}/download.zip" : null,
            'cover_url' => $cover ? ($cover->final_path ? "{$base}/photos/{$cover->id}/final.jpg" : "{$base}/photos/{$cover->id}/thumb.jpg") : null,
            'photos' => $photos->map(fn (ListingPhoto $p) => [
                'id' => $p->id,
                'position' => $p->position,
                'kind' => $p->kind,
                'status' => $p->status,
                'is_cover' => $p->is_cover,
                'width' => $p->width,
                'height' => $p->height,
                'thumb_url' => "{$base}/photos/{$p->id}/thumb.jpg",
                'final_url' => $p->final_path ? "{$base}/photos/{$p->id}/final.jpg?v=".$p->updated_at?->timestamp : null,
            ])->values(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
