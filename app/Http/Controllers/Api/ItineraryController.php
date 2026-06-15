<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Content;
use Illuminate\Http\Request;
use App\Models\SavedItinerary;
use App\Models\Itinerary;

class ItineraryController extends Controller
{

    public function generate(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Please login first.'
            ], 401);
        }
    
        $validated = $request->validate([
            'days' => 'required|integer|min:1|max:5',
            'interests' => 'nullable|array',
            'interests.*' => 'string',
            'budget' => 'nullable|string|in:hemat,menengah,premium',
            'use_wishlist' => 'boolean',
        ]);
    
        // FIX: Handle nullable fields dengan ?? operator
        $days = $validated['days'];
        $interests = $validated['interests'] ?? [];
        $budgetType = $validated['budget'] ?? 'menengah'; // ← Default value
        $useWishlist = $validated['use_wishlist'] ?? false;
    
        $candidates = $this->getCandidates($user, $interests, $useWishlist);
        
        if ($candidates->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Wishlist kosong atau tidak ada destinasi yang cocok.'
            ], 404);
        }
    
        $candidates = $candidates->take($days * 4);
        $itinerary = $this->assignToDays($candidates, $days);
        
        // Pakai $budgetType yang sudah di-handle default-nya
        $multiplier = config("itinerary.budget_multiplier.{$budgetType}", 1.0);
        $summary = $this->calculateSummary($itinerary, $multiplier);
    
        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'days' => $itinerary
            ]
        ]);
    }

    // Update signature method
    private function getCandidates($user, array $interests, bool $useWishlist)
    {
        // Prioritas 1: Ambil dari Wishlist
        if ($useWishlist) {
            $wishlistIds = $user->wishlists()
                ->where('visited', false)
                ->pluck('content_id');
                
            if ($wishlistIds->isNotEmpty()) {
                return Content::published()
                    ->whereIn('id', $wishlistIds)
                    ->with('category')
                    ->get();
            }
        }

        // Prioritas 2: Ambil berdasarkan interests
        $query = Content::published()->with('category');
        
        if (!empty($interests)) {
            $query->whereHas('category', fn($q) => $q->whereIn('slug', $interests));
        } else {
            $query->orderBy('is_featured', 'desc')->orderBy('created_at', 'desc');
        }

        return $query->limit(20)->get();
    }

    private function assignToDays($candidates, int $days): array
    {
        $timeSlots = ['pagi', 'siang', 'sore', 'malam'];
        $assigned = [];
        $pool = $candidates->values()->toArray();

        for ($day = 1; $day <= $days; $day++) {
            $dayPlan = ['day' => $day, 'theme' => $this->determineDayTheme($day, $days), 'slots' => []];

            foreach ($timeSlots as $slot) {
                $match = null;
                foreach ($pool as $index => $content) {
                    if (isset($content['assigned']) && $content['assigned']) continue;
                    $bestTimes = config("itinerary.best_time.{$content['category']['slug']}", ['pagi']);
                    if (in_array($slot, $bestTimes)) {
                        $match = $content;
                        unset($pool[$index]);
                        break;
                    }
                }
                if (!$match) {
                    foreach ($pool as $index => $content) {
                        if (!isset($content['assigned']) || !$content['assigned']) {
                            $match = $content;
                            unset($pool[$index]);
                            break;
                        }
                    }
                }
                if ($match) {
                    $match['assigned'] = true;
                    $match['time_slot'] = $slot;
                    $dayPlan['slots'][] = $this->formatPlace($match);
                }
            }
            $dayPlan['transport_tip'] = count($dayPlan['slots']) > 2 ? 'Disarankan sewa motor atau pakai ojek online.' : 'Lokasi berdekatan, bisa jalan kaki.';
            $assigned[] = $dayPlan;
        }
        return $assigned;
    }

    private function formatPlace(array $content): array
    {
        $catSlug = $content['category']['slug'] ?? 'default';
        $duration = config("itinerary.duration_hours.{$catSlug}", 2.0);
        $price = $content['ticket_price'] ? (int) filter_var($content['ticket_price'], FILTER_SANITIZE_NUMBER_INT) : config('itinerary.base_price', 35000);
        return [
            'content_id' => $content['id'],
            'title' => $content['title'],
            'slug' => $content['slug'],
            'lat' => $content['lat'],
            'lng' => $content['lng'],
            'category' => $content['category']['name'] ?? 'Umum',
            'estimated_duration' => "{$duration} jam",
            'time_slot' => $content['time_slot'] ?? 'pagi',
            'estimated_cost' => "Rp " . number_format($price, 0, ',', '.'),
            'maps_url' => "https://www.google.com/maps/search/?api=1&query={$content['lat']},{$content['lng']}",
            'tips' => $this->generateTip($catSlug, $content['time_slot'] ?? 'pagi'),
        ];
    }

    private function generateTip(string $category, string $timeSlot): string
    {
        $tips = [
            'kuliner' => ['siang' => 'Jam makan siang biasanya ramai, datang lebih awal.', 'malam' => 'Suasana malam lebih adem, cocok buat nongkrong.'],
            'sejarah' => ['pagi' => 'Datang sebelum jam 09.00 agar cuaca belum terik.'],
            'alam' => ['pagi' => 'Bawa sunscreen & air mineral.', 'sore' => 'Waktu terbaik buat sunset.'],
            'default' => ['pagi' => 'Pastikan cek jam operasional sebelum berkunjung.'],
        ];
        return $tips[$category][$timeSlot] ?? $tips['default']['pagi'];
    }

    private function determineDayTheme(int $day, int $totalDays): string
    {
        $themes = ['Eksplorasi Kota', 'Kuliner & Budaya', 'Alam & Petualangan', 'Relaksasi & Oleh-oleh'];
        return $themes[($day - 1) % count($themes)] ?? 'Jelajah Jogja';
    }

    private function calculateSummary(array $itinerary, float $multiplier): array
    {
        $totalDestinations = 0;
        $totalCost = 0;
        $highlights = []; // ← Inisialisasi array kosong
    
        foreach ($itinerary as $day) {
            foreach ($day['slots'] as $place) {
                $totalDestinations++;
                
                // Ambil cost (handle format "Rp 15.000")
                $cost = (int) filter_var($place['estimated_cost'], FILTER_SANITIZE_NUMBER_INT);
                $totalCost += ($cost * $multiplier);
                
                // Ambil title untuk highlights (maksimal 3)
                if (count($highlights) < 3 && !empty($place['title'])) {
                    $highlights[] = $place['title'];
                }
            }
        }
    
        return [
            'total_days' => count($itinerary),
            'total_destinations' => $totalDestinations,
            'estimated_total_budget' => 'Rp ' . number_format($totalCost, 0, ',', '.') . ' - Rp ' . number_format($totalCost * 1.3, 0, ',', '.'),
            'highlights' => $highlights, // ← Return array yang sudah diisi
        ];
    }

    /**
     * Save generated itinerary to database
     * POST /api/v1/itinerary/save
     */
    public function save(Request $request)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'days' => 'required|integer|min:1|max:5',
            'interests' => 'nullable|array',
            'budget_type' => 'nullable|string|in:hemat,menengah,premium',
            'itinerary_data' => 'required|array', // Full itinerary structure from generate()
            'total_destinations' => 'nullable|integer',
            'estimated_budget' => 'nullable|string',
        ]);

        $user = $request->user();
        
        $saved = $user->savedItineraries()->create([
            'title' => $validated['title'] ?? "Rencana {$validated['days']} Hari di Jogja",
            'days' => $validated['days'],
            'interests' => $validated['interests'] ?? [],
            'budget_type' => $validated['budget_type'] ?? 'menengah',
            'itinerary_data' => $validated['itinerary_data'],
            'total_destinations' => $validated['total_destinations'] ?? 0,
            'estimated_budget' => $validated['estimated_budget'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Itinerary berhasil disimpan!',
            'data' => [
                'id' => $saved->id,
                'title' => $saved->title,
                'created_at' => $saved->created_at->toISOString(),
            ]
        ], 201);
    }

    /**
     * Get user's saved itinerary history
     * GET /api/v1/itinerary/history
     */
    public function history(Request $request)
    {
        $user = $request->user();
        
        $histories = $user->savedItineraries()
            ->orderBy('created_at', 'desc')
            ->get(['id', 'title', 'days', 'budget_type', 'total_destinations', 'estimated_budget', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => $histories
        ]);
    }

    /**
     * Get detail of one saved itinerary
     * GET /api/v1/itinerary/history/{itinerary}
     */
    public function historyDetail(Request $request, SavedItinerary $itinerary)
    {
        // Pastikan user hanya bisa akses itinerary miliknya
        if ($itinerary->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // FIX: Explicitly return array dengan akses attribute langsung
        // Ini memastikan $casts ('array' untuk itinerary_data) diterapkan
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $itinerary->id,
                'title' => $itinerary->title,
                'days' => $itinerary->days,
                'interests' => $itinerary->interests, // cast: array
                'budget_type' => $itinerary->budget_type,
                'itinerary_data' => $itinerary->itinerary_data, // cast: array (JSON decoded)
                'total_destinations' => $itinerary->total_destinations,
                'estimated_budget' => $itinerary->estimated_budget,
                'created_at' => $itinerary->created_at->toISOString(),
                'updated_at' => $itinerary->updated_at->toISOString(),
            ]
        ]);
    }


        /**
     * Update saved itinerary
     * PUT /api/v1/itinerary/history/{id}
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        
        // Cari itinerary + pastikan milik user ini
        $itinerary = \App\Models\SavedItinerary::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();
        
        // Validasi input
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'days' => 'nullable|integer|min:1|max:7',
            'interests' => 'nullable|array',
            'interests.*' => 'string',
            'budget_type' => 'nullable|string|in:hemat,menengah,premium',
            'itinerary_data' => 'nullable|array',
            'total_destinations' => 'nullable|integer',
            'estimated_budget' => 'nullable|string',
        ]);
        
        // Update fields yang dikirim (partial update)
        foreach ($validated as $key => $value) {
            if ($value !== null) {
                // Khusus itinerary_data, kita merge biar nggak overwrite total
                if ($key === 'itinerary_data' && is_array($value)) {
                    $existing = $itinerary->itinerary_data ?? [];
                    $itinerary->itinerary_data = array_merge($existing, $value);
                } else {
                    $itinerary->$key = $value;
                }
            }
        }
        
        $itinerary->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Itinerary berhasil diupdate!',
            'data' => [
                'id' => $itinerary->id,
                'title' => $itinerary->title,
                'updated_at' => $itinerary->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * Delete itinerary
     * DELETE /api/v1/itinerary/history/{id}
     */
    public function destroy(Request $request, $id)
    {
        // Gunakan model yang BENAR: SavedItinerary
        $itinerary = \App\Models\SavedItinerary::find($id);

        // Cek apakah data ada
        if (!$itinerary) {
            return response()->json([
                'success' => false,
                'message' => 'Itinerary not found'
            ], 404);
        }

        // Security: Pastikan user hanya bisa hapus punya sendiri
        if ($itinerary->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $itinerary->delete();

        return response()->json([
            'success' => true,
            'message' => 'Itinerary berhasil dihapus.'
        ]);
    }

}