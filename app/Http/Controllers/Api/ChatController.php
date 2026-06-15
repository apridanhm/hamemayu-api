<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Content;

class ChatController extends Controller
{
    private const YOGYAKARTA_KEYWORDS = [
        'yogyakarta', 'jogja', 'jogjakarta', 'joged', 'gunung kidul', 'bantul', 
        'sleman', 'kulon progo', 'malioboro', 'keraton', 'prambanan', 'taman sari',
        'gudeg', 'angkringan', 'wedang', 'batik jogja', 'parangtritis', 'kotagede',
        'ugm', 'universitas gadjah mada', 'jogja city mall', 'titik nol kilometer',
        'alun-alun', 'benteng vredeburg', 'museum', 'candi', 'merapi', 'pantai jogja'
    ];

    public function chat(Request $request)
    {

        \Log::info('🦇 BATMAN_TRAP_CHAT_START', [
            'message' => $request->input('message'),
            'has_user' => $request->user() ? true : false,
            'user_id' => $request->user()?->id,
            'auth_header' => $request->header('Authorization') ? 'present' : 'missing',
        ]);

        $request->validate([
            'message' => 'required|string|max:1000',
            'session_id' => 'nullable|string',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'area' => 'nullable|string|max:100',
        ]);

        // === MANUAL OPTIONAL AUTH VIA SANCTUM ===
        // Cek apakah user mengirim token, kalau iya coba resolve user-nya
        $user = null;
        if ($request->bearerToken()) {
            $token = \Laravel\Sanctum\PersonalAccessToken::findToken($request->bearerToken());
            if ($token && $token->tokenable instanceof \App\Models\User) {
                $user = $token->tokenable;
                // Optional: update last_used_at biar token nggak expired
                $token->update(['last_used_at' => now()]);
            }
        }
        // === END MANUAL AUTH ===

        $message = $request->input('message');
        $sessionId = $request->input('session_id', 'guest');
        $cacheKey = "petruk_chat_{$sessionId}_" . md5($message);

        $userLat = $request->input('lat');
        $userLng = $request->input('lng');
        $userArea = $request->input('area');
        //$user = $request->user(); // Ambil user yang login (penting untuk wishlist)

        // FIX: Cek vague query DULU sebelum validasi scope
        if ($this->isVagueLocationQuery($message) && !$userLat && !$userLng && !$userArea) {
            $response = "Kamu lagi di area mana, Mas/Mbak? Malioboro, Keraton, Prambanan, atau mana? Nanti aku bantu rekomendasiin yang terdekat! 😊";
            Cache::put($cacheKey, $response, 120);
            return $this->formatResponse($response, $message);
        }

        // Cek apakah lokasi user di Yogyakarta
        $isInJogja = $this->isLocationInYogyakarta($userLat, $userLng);
        $isTravelQuery = $this->isTravelRelatedQuery($message);
        $isOnTopic = $isInJogja || $this->isYogyakartaRelated($message) || ($isTravelQuery && $isInJogja);

        if (!$isOnTopic) {
            $response = $this->getOutOfScopeResponse($message);
            Cache::put($cacheKey, $response, 300);
            return $this->formatResponse($response, $message);
        }

        // ==========================================
        // BARU: Handle Wishlist Intent (Prioritas Tinggi)
        // ==========================================
        // Cek intent SEBELUM cache, karena simpan wishlist itu action yang harus diproses real-time
        $intent = $this->detectIntent($message, [
            'pending_action' => session("chat_pending_action_{$user?->id}"),
            'pending_place_id' => session("chat_pending_place_id_{$user?->id}"),
            'pending_place_name' => session("chat_pending_place_name_{$user?->id}"),
        ]);

        // 1. Handle: User minta simpan ke wishlist (misal: "Simpan Gudeg Yuwono")
        if ($intent['intent'] === 'add_to_wishlist' && $user) {
            $place = $intent['place'];

            // === DEBUG LOGGING ===
            \Log::info('🎯 WISHLIST_BLOCK_ENTERED', [
                'user_id' => $user->id,
                'intent' => $intent,
                'place' => $place,
            ]);
            
            if ($place && isset($place['id'])) {
                // Simpan ke database (firstOrCreate biar aman kalau udah ada)
                \App\Models\Wishlist::firstOrCreate(
                    ['user_id' => $user->id, 'content_id' => $place['id']],
                    ['notes' => 'Ditambah via chat Petruk AI', 'priority' => 2]
                );
                
                // Clear session context
                session()->forget([
                    "chat_pending_action_{$user->id}",
                    "chat_pending_place_id_{$user->id}",
                    "chat_pending_place_name_{$user->id}",
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'bot_name' => 'Petruk',
                        'message' => "✅ **{$place['title']}** sudah kula simpan ke wishlist! 🎉\n\nMau kula rekomendasikan tempat lain, atau mau kula buatkan itinerary dari wishlist?",
                        'timestamp' => now()->toISOString(),
                        'suggestions' => [
                            "Lihat detail {$place['title']}",
                            "Tambahkan tempat lain ke wishlist",
                            "Buatkan itinerary dari wishlist",
                        ],
                        'action' => 'added_to_wishlist',
                        'content' => ['id' => $place['id'], 'title' => $place['title']],
                    ],
                ]);
            }
            
            // Kalau nama tempat nggak dikenali
            return response()->json([
                'success' => true,
                'data' => [
                    'bot_name' => 'Petruk',
                    'message' => "Maaf Mas/Mbak, kula kurang paham tempat mana yang mau disimpen. Coba sebutin nama lengkapnya sesuai yang ada di app ya? 😊",
                    'timestamp' => now()->toISOString(),
                    'suggestions' => ["Lihat rekomendasi lagi", "Tulis nama tempat lengkap"],
                ],
            ]);
        }

        // 2. Handle: User konfirmasi simpan (misal: jawab "iya" setelah ditawari)
        if ($intent['intent'] === 'confirm_wishlist' && $user && !empty($intent['place']['id'])) {
            $placeId = $intent['place']['id'];
            $placeTitle = $intent['place']['title'] ?? 'Tempat ini';
            
            // Cari detail content untuk validasi akhir
            $content = Content::find($placeId);
            
            if ($content) {
                \App\Models\Wishlist::firstOrCreate(
                    ['user_id' => $user->id, 'content_id' => $content->id],
                    ['notes' => 'Ditambah via chat Petruk AI (konfirmasi)', 'priority' => 2]
                );
                
                session()->forget([
                    "chat_pending_action_{$user->id}",
                    "chat_pending_place_id_{$user->id}",
                    "chat_pending_place_name_{$user->id}",
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'bot_name' => 'Petruk',
                        'message' => "✅ **{$placeTitle}** sudah kula simpan ke wishlist! 🎉\n\nMau kula tambahkan tempat lain, atau langsung kula buatkan itinerary?",
                        'timestamp' => now()->toISOString(),
                        'suggestions' => [
                            "Tambahkan tempat lain",
                            "Buatkan itinerary dari wishlist",
                            "Lihat wishlist saya",
                        ],
                        'action' => 'added_to_wishlist',
                        'content' => ['id' => $content->id, 'title' => $content->title],
                    ],
                ]);
            }
        }
        // ==========================================
        // 🔚 Akhir Logic Wishlist
        // ==========================================

        // Cek cache untuk response biasa (jika bukan action wishlist)
        $response = Cache::get($cacheKey);
        if ($response) {
            return $this->formatResponse($response, $message);
        }

        try {
            // Build location context untuk AI
            $locationContext = "";
            if ($userLat && $userLng) {
                $locationContext = "\n\nPOSISI USER: User berada di koordinat ({$userLat}, {$userLng}) - area Yogyakarta. Prioritaskan destinasi dalam radius 5km.";
            } elseif ($userArea) {
                $locationContext = "\n\nAREA USER: User berada di area {$userArea}, Yogyakarta.";
            }

            $aiResponse = $this->callGroqAPI($message, $locationContext);
            
            if ($aiResponse && $this->isResponseOnTopic($aiResponse)) {
                // ==========================================
                // BARU: Tawarkan Wishlist jika AI merekomendasikan tempat
                // ==========================================
                if ($user && preg_match('/(gudeg|candi|pantai|museum|hotel|penginapan|kuliner|wisata)/i', $message)) {
                    // Coba extract nama tempat pertama dari response AI untuk ditawarkan
                    // Kita pakai regex sederhana untuk menangkap nama setelah "Lokasi:"
                    if (preg_match('/Lokasi:\s*\[?([^\]\n\r\.]+?)(?:\]|\.|$)/iu', $aiResponse, $matches)) {
                        $offeredPlaceName = trim($matches[1], " []");
                        $offeredPlace = $this->extractPlaceNameFromDB($offeredPlaceName);
                        
                        if ($offeredPlace) {
                            // Simpan ke session untuk konteks jawaban "iya" nanti
                            session([
                                "chat_pending_action_{$user->id}" => 'offer_wishlist',
                                "chat_pending_place_id_{$user->id}" => $offeredPlace['id'],
                                "chat_pending_place_name_{$user->id}" => $offeredPlace['title'],
                            ]);
                            
                            // Tambahkan kalimat penawaran di akhir response AI
                            $aiResponse .= "\n\n_Mau kula simpan **{$offeredPlace['title']}** ke wishlist Mas/Mbak? Cukup balas 'iya' atau 'simpan' 😊_";
                        }
                    }
                }
                
                Cache::put($cacheKey, $aiResponse, 3600);
                return $this->formatResponse($aiResponse, $message);
            }
        } catch (\Exception $e) {
            Log::warning('Groq API failed: ' . $e->getMessage());
        }

        // Fallback rule-based
        $fallbackResponse = $this->generateRuleBasedResponse($message);
        Cache::put($cacheKey, $fallbackResponse, 300);
        return $this->formatResponse($fallbackResponse, $message);
    }

    private function isLocationInYogyakarta(?float $lat, ?float $lng): bool
    {
        if (!$lat || !$lng) return false;
        // Bounding box Yogyakarta (+- 30km dari pusat)
        return ($lat >= -8.2 && $lat <= -7.6 && $lng >= 110.1 && $lng <= 110.7);
    }

    private function isYogyakartaRelated(string $message): bool
    {
        $message = strtolower($message);
        foreach (self::YOGYAKARTA_KEYWORDS as $keyword) {
            if (str_contains($message, $keyword)) return true;
        }
        return false;
    }

    private function isTravelRelatedQuery(string $message): bool
    {
        $message = strtolower($message);
        $travelKeywords = ['wisata', 'destinasi', 'tempat wisata', 'liburan', 'jalan-jalan', 'tour', 'makan', 'kuliner', 'penginapan', 'hotel', 'transport', 'rute'];
        foreach ($travelKeywords as $kw) {
            if (str_contains($message, $kw)) return true;
        }
        return false;
    }

    private function isVagueLocationQuery(string $message): bool
    {
        $message = strtolower($message);
        $vagueKeywords = ['dekat sini', 'sekitar sini', 'dekat lokasi', 'di sini', 'sekitar sini dong', 'yang dekat aja', 'dekat sini aja', 'yang terdekat'];
        foreach ($vagueKeywords as $kw) {
            if (str_contains($message, $kw)) return true;
        }
        return false;
    }

    private function getOutOfScopeResponse(string $message): string
    {
        $responses = [
            "Maaf, aku hanya bisa bantu jawab seputar Yogyakarta. Kalau mau tau tentang \"$message\", mungkin bisa tanya ke asisten lain ya! 😊",
            "Waduh, itu di luar wilayah expert-ku nih. Aku khusus Jogja, Mas/Mbak! Coba tanya tentang kuliner, wisata, atau sejarah Yogyakarta aja 🙏",
        ];
        return $responses[array_rand($responses)];
    }

    private function generateGoogleMapsUrl(float $lat, float $lng, string $placeName = ''): string
    {
        $query = $placeName ? '&' . http_build_query(['query' => $placeName]) : '';
        return "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}{$query}";
    }

    private function generateMapsLinkFromPlaceName(string $placeName): ?array
    {
        // Bersihkan nama tempat
        $placeName = trim($placeName, " .,;:!?\"'[]()");
        
        // 1. Exact match (case-insensitive)
        $content = \App\Models\Content::published()
            ->whereRaw('LOWER(title) = ?', [strtolower($placeName)])
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->first();
        
        // 2. LIKE match kalau nggak ketemu exact
        if (!$content) {
            $content = \App\Models\Content::published()
                ->where(function($q) use ($placeName) {
                    $lowerPlace = strtolower($placeName);
                    $q->whereRaw('LOWER(title) LIKE ?', ["%{$lowerPlace}%"])
                      ->orWhereRaw('LOWER(slug) LIKE ?', ["%{$lowerPlace}%"]);
                })
                ->whereNotNull('lat')
                ->whereNotNull('lng')
                ->first();
        }
        
        if ($content) {
            return [
                'label' => $content->title,
                'url' => $this->generateGoogleMapsUrl($content->lat, $content->lng, $content->title),
                'source' => 'database',
            ];
        }
        
        // 3. Fallback: Google Maps search query
        return [
            'label' => $placeName,
            'url' => "https://www.google.com/maps/search/?api=1&query=" . urlencode($placeName . " Yogyakarta"),
            'source' => 'search',
        ];
    }

    private function callGroqAPI(string $message, string $locationContext = ''): ?string
    {
        $apiKey = env('GROQ_API_KEY');
        if (!$apiKey) return null;

        $systemPrompt = <<<PROMPT
Kamu adalah Petruk, asisten virtual ramah yang khusus membantu wisatawan dan warga Yogyakarta. 

ATURAN PENTING:
1. JAWAB HANYA pertanyaan seputar Yogyakarta. Jika ditanya hal di luar Yogyakarta, tolak dengan sopan.
2. Gaya bicara: santai, ramah, pakai sedikit bahasa Jawa halus ("Mas", "Mbak", "Nggih").
3. Jawaban maksimal 3-4 kalimat.

FITUR NAMA TEMPAT:
- Jika user tanya rekomendasi tempat, sebutkan NAMA TEMPAT yang spesifik (contoh: "Gudeg Yuwono", "Candi Prambanan").
- JANGAN buat koordinat atau URL maps sendiri. Cukup tulis nama tempat.
- Format akhir response HARUS menyertakan: "🗺️ Lokasi: Nama Tempat" (boleh pakai [ ] atau nggak, backend akan handle)
- Backend akan otomatis generate Google Maps link yang benar berdasarkan nama tersebut.

CONTOH JAWABAN BAIK:
- "Nggih Mas, untuk ke Candi Prambanan dari Malioboro bisa naik TransJogja koridor 1A. Tiket masuk sekitar Rp 50.000. 🗺️ Lokasi: Candi Prambanan"
- "Mbak, kalau mau gudeg autentik, coba Gudeg Yuwono atau Gudeg Pawon. Harga mulai Rp 15.000. 🗺️ Lokasi: Gudeg Yuwono"
{$locationContext}
PROMPT;

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(15)->post('https://api.groq.com/openai/v1/chat/completions', [
            'model' => 'llama-3.1-8b-instant',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $message],
            ],
            'temperature' => 0.7,
            'max_tokens' => 300,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return $data['choices'][0]['message']['content'] ?? null;
        }
        return null;
    }

    private function isResponseOnTopic(string $response): bool
    {
        $response = strtolower($response);
        $offTopic = ['bali', 'jakarta', 'bandung', 'surabaya', 'lombok', 'raja ampat'];
        foreach ($offTopic as $kw) {
            if (str_contains($response, $kw) && !str_contains($response, 'yogyakarta') && !str_contains($response, 'jogja')) {
                return false;
            }
        }
        return true;
    }

    private function generateRuleBasedResponse(string $message): string
    {
        $message = strtolower($message);
        $responses = [
            'kuliner' => ['keywords' => ['makan', 'gudeg', 'angkringan', 'wedang', 'jajan', 'kuliner', 'makanan'], 'reply' => 'Wah, soal kuliner Jogja aku jagoan! 🍜 Coba Gudeg Yuwono untuk yang legendaris. 🗺️ Lokasi: Gudeg Yuwono'],
            'wisata' => ['keywords' => ['wisata', 'jalan-jalan', 'destinasi', 'tempat', 'liburan', 'tour'], 'reply' => 'Jogja punya banyak destinasi keren! 🏛️ Keraton, Prambanan, Pantai Parangtritis. Mau tau detail salah satunya? 🗺️ Lokasi: Keraton Yogyakarta'],
            'transport' => ['keywords' => ['naik apa', 'transport', 'bus', 'ojek', 'grab', 'gojek', 'rute', 'cara ke'], 'reply' => 'Untuk transportasi di Jogja, ada TransJogja (bus murah), ojek online, atau sewa motor. Mau tau rute ke tempat tertentu?'],
        ];
        foreach ($responses as $data) {
            foreach ($data['keywords'] as $keyword) {
                if (str_contains($message, $keyword)) return $data['reply'];
            }
        }
        return "Hai, aku Petruk! 👋 Aku belum paham banget soal \"$message\", tapi aku bisa bantu kamu jelajahi Yogyakarta! Coba tanya tentang: kuliner, wisata, atau transportasi. 🙏";
    }

    private function formatResponse(string $message, string $originalQuery): array
    {
        $mapsLink = null;
        
        // FIX: Regex fleksibel - match dengan atau tanpa kurung siku
        // Pattern: "Lokasi:" + optional whitespace + optional [ + capture name + optional ] or end of string
        if (preg_match('/Lokasi:\s*\[?([^\]\n\r\.]+?)(?:\]|\.|$)/iu', $message, $matches)) {
            $placeName = trim($matches[1], " []");
            if (!empty($placeName) && strlen($placeName) > 2) {
                $mapsLink = $this->generateMapsLinkFromPlaceName($placeName);
            }
        }
        
        return [
            'success' => true,
            'data' => [
                'bot_name' => 'Petruk',
                'message' => $message,
                'timestamp' => now()->toISOString(),
                'suggestions' => $this->getQuickPrompts($originalQuery),
                'is_ai_generated' => env('GROQ_API_KEY') ? true : false,
                'maps' => $mapsLink,
            ],
        ];
    }

    private function getQuickPrompts(string $lastMessage): array
    {
        return [
            'Rekomendasi kuliner halal di Jogja',
            'Cara ke Candi Prambanan dari Malioboro',
            'Tempat wisata gratis untuk mahasiswa',
            'Jadwal pertunjukan budaya minggu ini',
            'Penginapan budget dekat Tugu Jogja',
        ];
    }

    /**
     * Extract nama tempat dari pesan user secara dinamis dari Database
     * Mengembalikan: ['id' => 1, 'title' => 'Gudeg Yuwono', 'slug' => 'gudeg-yuwono']
     */
    private function extractPlaceNameFromDB(string $message): ?array
    {
        // Ambil semua judul tempat yang published (di-cache 1 jam biar ringan)
        $places = Cache::remember('chat_place_titles', 3600, function () {
            return Content::where('status', 'published')
                ->get(['id', 'title', 'slug']);
        });

        $messageLower = strtolower(trim($message));
        $bestMatch = null;
        $longestMatchLength = 0;

        // Cari tempat yang namanya ada di dalam pesan user
        foreach ($places as $place) {
            $titleLower = strtolower($place->title);
            
            // Cek apakah nama tempat muncul di pesan user
            if (str_contains($messageLower, $titleLower)) {
                // Ambil yang paling panjang (biar "Gudeg Yuwono" menang atas "Gudeg")
                if (strlen($titleLower) > $longestMatchLength) {
                    $longestMatchLength = strlen($titleLower);
                    $bestMatch = [
                        'id' => $place->id,
                        'title' => $place->title,
                        'slug' => $place->slug,
                    ];
                }
            }
        }

        return $bestMatch;
    }

    private function detectIntent(string $message, array $context = []): array
    {
        $lower = strtolower($message);
        
        // 1. Intent: Simpan ke wishlist
        if (preg_match('/(simpan|tambah|masukin|save|add).*?(wishlist|favorit|simpanan)/i', $lower)) {
            $place = $this->extractPlaceNameFromDB($message);
            
            return [
                'intent' => 'add_to_wishlist',
                'place' => $place, // Langsung dapat array ['id', 'title', 'slug']
                'confidence' => $place ? 'high' : 'low'
            ];
        }
        
        // 2. Intent: Konfirmasi ("iya", "oke", "gas")
        if (preg_match('/^(iya|yes|oke|gas|boleh|ayo|yuk|ok)/i', $lower) && ($context['pending_action'] ?? null) === 'offer_wishlist') {
            return [
                'intent' => 'confirm_wishlist',
                'place' => [
                    'id' => $context['pending_place_id'] ?? null,
                    'title' => $context['pending_place_name'] ?? null,
                ],
            ];
        }
        
        // 3. Intent: Rekomendasi biasa
        return ['intent' => 'recommendation'];
    }


}