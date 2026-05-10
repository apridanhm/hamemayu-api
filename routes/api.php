<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\ItineraryController;

// Prefix versioning biar gampang maintenance
Route::prefix('v1')->group(function () {
    
    // === PUBLIC ROUTES (No Auth Required) ===
    
    // Kategori (6 Pilar)
    Route::get('/categories', [CategoryController::class, 'index']);

    // Konten Utama
    Route::get('/contents', [ContentController::class, 'index']);
    Route::get('/contents/featured', [ContentController::class, 'featured']);
    Route::get('/contents/{slug}', [ContentController::class, 'show']);

    // Peta Interaktif
    Route::get('/map-markers', [ContentController::class, 'mapMarkers']);

    // Chat Petruk AI
    Route::post('/chat', [ChatController::class, 'chat']);

    // Google Auth Flow (public endpoints)
    Route::get('/auth/google/redirect', [AuthController::class, 'redirectToGoogle']);
    Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);


    // === PROTECTED ROUTES (Require Login via Sanctum) ===
    Route::middleware('auth:sanctum')->group(function () {
        
        // User Profile (BARU - untuk tampilkan data user login)
        Route::get('/user/profile', function (\Illuminate\Http\Request $request) {
            return response()->json([
                'success' => true,
                'data' => $request->user()
            ]);
        });

        // Wishlist CRUD
        Route::apiResource('/wishlist', WishlistController::class);

        // Itinerary Planner (PINDAHKAN KE SINI - inside auth group!)
        Route::post('/itinerary/generate', [ItineraryController::class, 'generate']);
        Route::post('/itinerary/save', [ItineraryController::class, 'save']);
        Route::get('/itinerary/history', [ItineraryController::class, 'history']);
        Route::get('/itinerary/history/{itinerary}', [ItineraryController::class, 'historyDetail']);
        Route::put('/itinerary/history/{id}', [ItineraryController::class, 'update']);
        Route::delete('/itinerary/history/{id}', [ItineraryController::class, 'destroy']);

        // Logout
        Route::post('/auth/logout', [AuthController::class, 'logout']);
    });

});