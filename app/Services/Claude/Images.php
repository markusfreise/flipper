<?php

namespace App\Services\Claude;

use App\Models\ListingPhoto;
use App\Services\Images\ExifNormalizer;

final class Images
{
    /**
     * Content-Blöcke für die Vision-Eingabe: verkleinerte JPEGs (lange Kante 1568 px) plus Beschriftung.
     *
     * @param  iterable<ListingPhoto>  $photos
     * @return list<array<string, mixed>>
     */
    public static function blocks(iterable $photos, ExifNormalizer $normalizer): array
    {
        $blocks = [];
        foreach ($photos as $photo) {
            $label = sprintf(
                'Foto %d (%s, Original %d×%d px):',
                $photo->position,
                $photo->isDetail() ? 'Nahaufnahme/Mangel' : 'Übersicht',
                $photo->width ?? 0,
                $photo->height ?? 0,
            );
            $blocks[] = ['type' => 'text', 'text' => $label];
            $blocks[] = [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'mediaType' => 'image/jpeg',
                    'data' => base64_encode($normalizer->visionJpeg($photo->absolute($photo->original_path))),
                ],
            ];
        }

        return $blocks;
    }
}
