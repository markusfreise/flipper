<?php

namespace App\Models;

use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory, HasUuids;

    public const STATUSES = ['photos', 'identifying', 'identified', 'researching', 'questions', 'writing', 'ready', 'failed'];

    protected $fillable = [
        'user_id', 'status', 'status_before_failure', 'product', 'photo_check', 'research',
        'answers', 'pricing', 'text', 'zip_path', 'text_revisions', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'product' => 'array',
            'photo_check' => 'array',
            'research' => 'array',
            'answers' => 'array',
            'pricing' => 'array',
            'text' => 'array',
            'text_revisions' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ListingPhoto::class)->orderBy('position');
    }

    /** Relativer Ordner auf der local-Disk. */
    public function directory(): string
    {
        return 'listings/'.$this->id;
    }

    public function deleteFiles(): void
    {
        Storage::disk('local')->deleteDirectory($this->directory());
    }

    public function cover(): ?ListingPhoto
    {
        return $this->photos->firstWhere('is_cover', true) ?? $this->photos->first();
    }
}
