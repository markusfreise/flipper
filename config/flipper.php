<?php

return [
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        // PRD nannte claude-sonnet-4-5; das Modell wird am 30.11.2026 abgeschaltet.
        // Default ist daher der aktuelle Sonnet, per .env überschreibbar.
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5-5'),
        'max_web_searches' => (int) env('ANTHROPIC_MAX_WEB_SEARCHES', 5),
        'timeout' => 180.0,
    ],

    // Nur lokal: Luna-Fixtures statt Claude-API (kein API-Key nötig, keine Kosten).
    'fake_ai' => (bool) env('FLIPPER_FAKE_AI', false),

    'rembg' => [
        'url' => env('REMBG_URL', 'http://127.0.0.1:7000'),
        'model' => env('REMBG_MODEL', 'isnet-general-use'),
        'timeout' => (int) env('REMBG_TIMEOUT', 180),
    ],

    'retention_days' => (int) env('LISTING_RETENTION_DAYS', 90),
    'pickup_location' => env('PICKUP_LOCATION', 'Bielefeld-West'),
    'max_photos' => 10,
    'max_photo_kb' => 15 * 1024,
    'max_text_revisions' => 5,
    'min_original_edge_px' => 2000,

    'ntfy' => [
        'url' => env('NTFY_URL'),
        'topic' => env('NTFY_TOPIC', 'flipper'),
    ],

    'deploy_secret' => env('FLIPPER_DEPLOY_SECRET'),

    // Versand: Kleinanzeigen bietet eigene Versandarten an, der Käufer wählt und zahlt.
    'shipping' => [
        'kleinanzeigen_text' => 'über Kleinanzeigen versichert, ab 3,99 €',
        // DHL Privatkundenpreise Stand 2026 (online frankiert). Maße in cm (L×B×H), Gewicht in g.
        'dhl' => [
            ['service' => 'DHL Päckchen S', 'max_cm' => [35, 25, 10], 'max_g' => 2000, 'price_eur' => 4.19],
            ['service' => 'DHL Päckchen M', 'max_cm' => [60, 30, 15], 'max_g' => 2000, 'price_eur' => 5.19],
            ['service' => 'DHL Paket bis 2 kg', 'max_cm' => [60, 30, 15], 'max_g' => 2000, 'price_eur' => 6.19],
        ],
        // Zuschlag für Verpackung
        'packaging_cm' => 1,
        'packaging_g' => 200,
    ],
];
