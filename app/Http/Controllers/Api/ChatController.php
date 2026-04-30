<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        $request->validate([
            'message' => 'required|string|max:1000',
            'session_id' => 'nullable|string',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'area' => 'nullable|string|max:100',
        ]);

        $message = $request->input('message');
        $sessionId = $request->input('session_id', 'guest');
        $cacheKey = "petruk_chat_{$sessionId}_" . md5($message);

        $userLat = $request->input('lat');
        $userLng = $request->input('lng');
        $userArea = $request->input('area');

        // ✅ FIX: Cek vague query DULU sebelum validasi scope
        if ($this->isVagueLocationQuery($message) && !$userLat && !$userLng && !$userArea) {
            $response = "Kamu lagi di area mana, Mas/Mbak? Malioboro, Keraton, Prambanan, atau mana? Nanti aku bantu rekomendasiin yang terdekat! 😊";
            Cache::put($cacheKey, $response, 120);
            return $this->formatResponse($response, $message);
        }

        // Cek apakah lokasi user di Yogyakarta (jika ada koordinat)
        $isInJogja = $this->isLocationInYogyakarta($userLat, $userLng);

        // ✅ FIX: Jika vague query + travel keyword + ada lokasi, anggap on-topic
        $isTravelQuery = $this->isTravelRelatedQuery($message);
        $isOnTopic = $isInJogja || $this->isYogyakartaRelated($message) || ($isTravelQuery && $isInJogja);

        if (!$isOnTopic) {
            $response = $this->getOutOfScopeResponse($message);
            Cache::put($cacheKey, $response, 300);
            return $this->formatResponse($response, $message);
        }

        // Cek cache
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
        
        // ✅ FIX: Regex fleksibel - match dengan atau tanpa kurung siku
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
}