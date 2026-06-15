<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WishlistController extends Controller
{
    // GET /api/v1/wishlist
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Ambil wishlist user ini, urutkan berdasarkan priority lalu created_at
        $wishlists = $user->wishlists()
            ->with('content.category') // Load data content dan category sekaligus
            ->orderBy('priority', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $wishlists
        ]);
    }

    // POST /api/v1/wishlist
    public function store(Request $request)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'content_id' => 'required|exists:contents,id',
            'notes' => 'nullable|string|max:500',
            'priority' => 'nullable|integer|min:0',
        ]);

        // Cek apakah sudah pernah di-add
        $existing = Wishlist::where('user_id', $user->id)
                            ->where('content_id', $validated['content_id'])
                            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Destinasi sudah ada di wishlist kamu.'
            ], 422);
        }

        $wishlist = $user->wishlists()->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil ditambahkan ke wishlist!',
            'data' => $wishlist
        ], 201);
    }

    // PUT /api/v1/wishlist/{id}
    public function update(Request $request, Wishlist $wishlist)
    {
        // Cek kepemilikan
        if ($wishlist->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
            'visited' => 'boolean',
            'priority' => 'nullable|integer|min:0',
        ]);

        $wishlist->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Wishlist diupdate!',
            'data' => $wishlist
        ]);
    }

    // DELETE /api/v1/wishlist/{id}
    public function destroy(Request $request, Wishlist $wishlist)
    {
        // Cek kepemilikan
        if ($wishlist->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $wishlist->delete();

        return response()->json([
            'success' => true,
            'message' => 'Dihapus dari wishlist.'
        ]);
    }
}