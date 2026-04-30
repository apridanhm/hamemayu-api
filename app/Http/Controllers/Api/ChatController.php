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
        ]);

        $message = $request->input('message');
        $sessionId = $request->input('session_id', 'guest');
        $cacheKey = "petruk_chat_{$sessionId}_" . md5($message);

        // Coba ambil dari cache dulu
        $response = Cache::get($cacheKey);
        if ($response) {
            return $this->formatResponse($response, $message);
        }

        // 1. Cek apakah pertanyaan terkait Yogyakarta
        if (!$this->isYogyakartaRelated($message)) {
            $response = $this->getOutOfScopeResponse($message);
            Cache::put($cacheKey, $response, 300);
            return $this->formatResponse($response, $message);
        }

        // 2. Coba panggil AI API (Groq)
        try {
            $aiResponse = $this->callGroqAPI($message);
            if ($aiResponse) {
                // 3. Post-filter: pastikan response tetap on-topic
                if ($this->isResponseOnTopic($aiResponse)) {
                    Cache::put($cacheKey, $aiResponse, 3600);
                    return $this->formatResponse($aiResponse, $message);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Groq API failed: ' . $e->getMessage());
            // Fallback ke rule-based jika API error
        }

        // 4. Fallback ke rule-based responses
        $fallbackResponse = $this->generateRuleBasedResponse($message);
        Cache::put($cacheKey, $fallbackResponse, 300);
        
        return $this->formatResponse($fallbackResponse, $message);
    }

    private function isYogyakartaRelated(string $message): bool
    {
        $message = strtolower($message);
        
        foreach (self::YOGYAKARTA_KEYWORDS as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }
        
        // Cek juga pertanyaan umum tentang travel/tourism yang mungkin implicit tentang Jogja
        $travelKeywords = ['wisata', 'destinasi', 'tempat wisata', 'liburan', 'jalan-jalan', 'tour'];
        foreach ($travelKeywords as $kw) {
            if (str_contains($message, $kw)) {
                return true; // Asumsikan tentang Jogja karena ini aplikasi HamemayuJogja
            }
        }
        
        return false;
    }

    private function getOutOfScopeResponse(string $message): string
    {
        $responses = [
            "Maaf, aku hanya bisa bantu jawab seputar Yogyakarta. Kalau mau tau tentang \"$message\", mungkin bisa tanya ke asisten lain ya! 😊",
            "Waduh, itu di luar wilayah expert-ku nih. Aku khusus Jogja, Mas/Mbak! Coba tanya tentang kuliner, wisata, atau sejarah Yogyakarta aja 🙏",
            "Aku Petruk, penjaga informasi Yogyakarta! Untuk pertanyaan di luar Jogja, aku belum bisa bantu. Tapi kalau soal Jogja, aku siap 24 jam! 💪",
        ];
        return $responses[array_rand($responses)];
    }

    private function callGroqAPI(string $message): ?string
    {
        $apiKey = env('GROQ_API_KEY');
        if (!$apiKey) {
            return null;
        }

        $systemPrompt = <<<PROMPT
Kamu adalah Petruk, asisten virtual ramah yang khusus membantu wisatawan dan warga Yogyakarta. 

ATURAN PENTING:
1. JAWAB HANYA pertanyaan seputar Yogyakarta: wisata, kuliner, sejarah, budaya, transportasi, acara, dan kehidupan sehari-hari di Jogja.
2. Jika ditanya hal di luar Yogyakarta, tolak dengan sopan dan arahkan untuk bertanya seputar Jogja.
3. Gaya bicara: santai, ramah, pakai sedikit bahasa Jawa halus (seperti "Mas", "Mbak", "Nggih", "Monggo"), tapi tetap mudah dipahami.
4. Jawaban maksimal 3-4 kalimat, informatif tapi tidak bertele-tele.
5. Jika tidak yakin, lebih baik akui dan sarankan sumber informasi resmi.

Contoh jawaban baik:
- "Nggih Mas, untuk ke Candi Prambanan dari Malioboro bisa naik TransJogja koridor 1A, turun di halte Prambanan. Tiket masuk sekitar Rp 50.000 untuk wisatawan domestik."
- "Mbak, kalau mau gudeg autentik, coba Gudeg Yuwono atau Gudeg Pawon. Buka dari pagi sampai malam, harga mulai Rp 15.000."

Contoh penolakan sopan:
- "Maaf Mbak, aku hanya expert seputar Yogyakarta. Untuk pertanyaan tentang Bali, mungkin bisa tanya ke asisten daerah sana ya! 😊"
PROMPT;

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(10)->post('https://api.groq.com/openai/v1/chat/completions', [
            'model' => 'llama3-8b-8192',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $message],
            ],
            'temperature' => 0.7,
            'max_tokens' => 256,
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
        // Jika response mengandung penolakan atau referensi ke luar Jogja, anggap off-topic
        $offTopicIndicators = ['bali', 'jakarta', 'bandung', 'surabaya', 'lombok', 'raja ampat', 'di luar jogja', 'tidak tahu', 'maaf tidak bisa'];
        
        foreach ($offTopicIndicators as $indicator) {
            if (str_contains($response, $indicator) && !str_contains($response, 'yogyakarta') && !str_contains($response, 'jogja')) {
                return false;
            }
        }
        return true;
    }

    private function generateRuleBasedResponse(string $message): string
    {
        $message = strtolower($message);
        
        $responses = [
            'kuliner' => [
                'keywords' => ['makan', 'gudeg', 'angkringan', 'wedang', 'jajan', 'kuliner', 'makanan'],
                'reply' => 'Wah, soal kuliner Jogja aku jagoan! 🍜 Coba Gudeg Yuwono untuk yang legendaris, Angkringan Lik Man buat nongkrong malam, atau Wedang Ronde Pak Kris untuk yang anget-anget. Mau rekomendasi spesifik?'
            ],
            'wisata' => [
                'keywords' => ['wisata', 'jalan-jalan', 'destinasi', 'tempat', 'liburan', 'tour'],
                'reply' => 'Jogja punya banyak destinasi keren! 🏛️ Keraton untuk sejarah, Prambanan untuk candi megah, Pantai Parangtritis untuk sunset, atau Malioboro untuk belanja. Mau tau detail salah satunya?'
            ],
            'transport' => [
                'keywords' => ['naik apa', 'transport', 'bus', 'ojek', 'grab', 'gojek', 'rute', 'cara ke'],
                'reply' => 'Untuk transportasi di Jogja, ada TransJogja (bus murah), ojek online, atau sewa motor. Mau tau rute ke tempat tertentu? Sebut aja destinasi-nya! 🛵'
            ],
            'akomodasi' => [
                'keywords' => ['hotel', 'penginapan', 'homestay', 'menginap', 'tidur'],
                'reply' => 'Banyak pilihan penginapan di Jogja, Mas/Mbak! 🏨 Dari budget hostel di Sosrowijayan, homestay nyaman di Prawirotaman, sampai hotel bintang di pusat kota. Mau rekomendasi sesuai budget?'
            ],
            'acara' => [
                'keywords' => ['acara', 'event', 'festival', 'pertunjukan', 'wayang', 'karnaval'],
                'reply' => 'Jogja sering ada event keren! 🎭 Cek Instagram @disbudpar_jogja atau @jogjatourism untuk update festival, wayang kulit, atau karnaval budaya. Ada event khusus yang dicari?'
            ],
        ];

        foreach ($responses as $data) {
            foreach ($data['keywords'] as $keyword) {
                if (str_contains($message, $keyword)) {
                    return $data['reply'];
                }
            }
        }

        // Default fallback dengan persona Petruk
        return "Hai, aku Petruk! 👋 Aku belum paham banget soal \"$message\", tapi aku bisa bantu kamu jelajahi Yogyakarta! Coba tanya tentang: kuliner, wisata, transportasi, penginapan, atau acara di Jogja. Aku di sini buat bantu! 🙏";
    }

    private function formatResponse(string $message, string $originalQuery): array
    {
        return [
            'success' => true,
            'data' => [
                'bot_name' => 'Petruk',
                'message' => $message,
                'timestamp' => now()->toISOString(),
                'suggestions' => $this->getQuickPrompts($originalQuery),
                'is_ai_generated' => str_contains($message, 'Nggih') || str_contains($message, 'Mas') || str_contains($message, 'Mbak'), // Heuristic: AI responses use Javanese honorifics
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