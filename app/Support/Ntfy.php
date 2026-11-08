<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class Ntfy
{
    public static function send(string $title, string $message, string $priority = 'default'): void
    {
        $url = config('flohmarkt.ntfy.url');
        if (! $url) {
            return;
        }

        try {
            Http::timeout(5)
                ->withHeaders(['Title' => $title, 'Priority' => $priority, 'Tags' => 'flohmarkt'])
                ->withBody($message, 'text/plain')
                ->post(rtrim($url, '/').'/'.config('flohmarkt.ntfy.topic'));
        } catch (Throwable $e) {
            Log::warning('ntfy nicht erreichbar: '.$e->getMessage());
        }
    }
}
