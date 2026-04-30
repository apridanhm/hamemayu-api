<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\ChatController;

// Prefix versioning biar gampang maintenance
Route::prefix('v1')->group(function () {
    // Kategori (6 Pilar)
    Route::get('/categories', [CategoryController::class, 'index']);

    // Konten Utama
    Route::get('/contents', [ContentController::class, 'index']);
    Route::get('/contents/featured', [ContentController::class, 'featured']);
    Route::get('/contents/{slug}', [ContentController::class, 'show']);

    // Peta Interaktif
    Route::get('/map-markers', [ContentController::class, 'mapMarkers']);

    // chat patruk
    Route::post('/chat', [ChatController::class, 'chat']);
});