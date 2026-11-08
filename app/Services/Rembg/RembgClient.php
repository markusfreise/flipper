<?php

namespace App\Services\Rembg;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** HTTP-Client für den rembg-Dienst (`rembg s`, Docker auf LXC 111). */
class RembgClient
{
    public function __construct(
        private readonly string $url,
        private readonly string $model,
        private readonly int $timeout,
    ) {}

    /** Liefert das PNG mit Alphakanal. */
    public function removeBackground(string $imagePath): string
    {
        $response = Http::timeout($this->timeout)
            ->attach('file', fopen($imagePath, 'r'), basename($imagePath))
            ->post(rtrim($this->url, '/').'/api/remove', [
                'model' => $this->model,
                'a' => 'true',   // alpha matting wie im Referenz-Script
                'af' => '240',
                'ab' => '10',
                'ae' => '10',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('rembg antwortet mit HTTP '.$response->status());
        }

        $body = $response->body();
        if (! str_starts_with($body, "\x89PNG")) {
            throw new RuntimeException('rembg lieferte kein PNG.');
        }

        return $body;
    }
}
