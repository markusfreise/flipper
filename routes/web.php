<?php

use App\Http\Controllers\Api\ListingController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\DeployHookController;
use Illuminate\Support\Facades\Route;

Route::post('deploy-hook', DeployHookController::class)->middleware('throttle:10,1');

// Dateien über die Web-Session (funktioniert auch ohne Referer, z. B. Downloads auf dem iPhone)
Route::middleware('auth')->prefix('files/listings/{listing}')->group(function () {
    Route::get('download.zip', [ListingController::class, 'download']);
    Route::get('photos/{photo}/final.jpg', [PhotoController::class, 'final']);
    Route::get('photos/{photo}/thumb.jpg', [PhotoController::class, 'thumb']);
});

// SPA: alles außer /api und /deploy-hook rendert die Vue-App.
Route::view('/{any?}', 'app')->where('any', '^(?!api|files|deploy-hook|up|sanctum).*$');
