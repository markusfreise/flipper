<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ListingController;
use App\Http\Controllers\Api\PhotoController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        Route::get('listings', [ListingController::class, 'index']);
        Route::post('listings', [ListingController::class, 'store']);
        Route::get('listings/{listing}', [ListingController::class, 'show']);
        Route::delete('listings/{listing}', [ListingController::class, 'destroy']);
        Route::post('listings/{listing}/identify', [ListingController::class, 'identify']);
        Route::put('listings/{listing}/product', [ListingController::class, 'product']);
        Route::put('listings/{listing}/answers', [ListingController::class, 'answers']);
        Route::post('listings/{listing}/text/revise', [ListingController::class, 'revise']);
        Route::post('listings/{listing}/retry', [ListingController::class, 'retry']);

        Route::post('listings/{listing}/photos', [PhotoController::class, 'store']);
        Route::patch('listings/{listing}/photos/{photo}', [PhotoController::class, 'update']);
        Route::delete('listings/{listing}/photos/{photo}', [PhotoController::class, 'destroy']);
    });
});
