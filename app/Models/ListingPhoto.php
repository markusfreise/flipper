<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ListingPhoto extends Model
{
    use HasUuids;

    protected $fillable = [
        'listing_id', 'position', 'kind', 'original_path', 'upload_path', 'cutout_path', 'final_path',
        'width', 'height', 'status', 'is_cover',
    ];

    protected function casts(): array
    {
        return ['is_cover' => 'boolean', 'width' => 'integer', 'height' => 'integer', 'position' => 'integer'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function absolute(string $relative): string
    {
        return Storage::disk('local')->path($relative);
    }

    public function isDetail(): bool
    {
        return $this->kind === 'detail';
    }

    /** Hat das Foto seinen Endzustand (fertiges JPG vorhanden)? */
    public function isFinished(): bool
    {
        return $this->final_path !== null;
    }
}
