<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Content;
use App\Models\Category;

class ImportOpenStreetMap extends Command
{
    protected $signature = 'osm:import {--keyword=} {--category=kuliner} {--limit=30}';
    protected $description = 'Import places from OpenStreetMap (Nominatim) for Yogyakarta';

    // Bounding box Yogyakarta (minLon,minLat,maxLon,maxLat)
    private $yogyaBounds = '110.1,-8.2,110.7,-7.6';

    public function handle()
    {
        $keyword = $this->option('keyword');
        if (!$keyword) {
            $this->error('Gunakan: php artisan osm:import --keyword="hotel"');
            return 1;
        }

        $categorySlug = $this->option('category');
        $limit = (int) $this->option('limit');

        $this->info("Mencari '{$keyword}' di Yogyakarta via OpenStreetMap...");

        // 1. Fetch data dari Nominatim API
        $response = Http::timeout(10)->get('https://nominatim.openstreetmap.org/search', [
            'q' => "{$keyword} Yogyakarta",
            'format' => 'json',
            'limit' => $limit,
            'viewbox' => $this->yogyaBounds,
            'bounded' => 1, // Hanya hasil di dalam box
            'addressdetails' => 1,
        ]);

        if (!$response->successful()) {
            $this->error("API Error: HTTP " . $response->status());
            return 1;
        }

        $places = $response->json();
        if (empty($places)) {
            $this->warn('Tidak ada hasil ditemukan.');
            return 0;
        }

        // 2. Pastikan kategori tersedia
        $category = Category::firstOrCreate(
            ['slug' => $categorySlug],
            ['name' => ucfirst($categorySlug), 'icon' => 'map-pin', 'color' => '#3B82F6']
        );

        $saved = 0;
        $skipped = 0;

        // 3. Proses & simpan satu per satu
        foreach ($places as $place) {
            if ($saved >= $limit) break;

            $lat = (float) $place['lat'];
            $lon = (float) $place['lon'];
            $rawName = $place['name'] ?? $place['display_name'] ?? 'Unknown';
            $name = $this->cleanName($rawName); // Hapus aksara Jawa/special char

            // Filter manual: pastikan alamat mengandung Yogyakarta/DIY
            $addressStr = strtolower($place['display_name'] ?? '');
            if (!str_contains($addressStr, 'yogyakarta') && !str_contains($addressStr, 'diy')) {
                $skipped++; continue;
            }

            // Cek duplikat (nama atau koordinat mirip)
            if (Content::where('title', $name)
                ->orWhere(function($q) use ($lat, $lon) {
                    $q->whereBetween('lat', [$lat - 0.0001, $lat + 0.0001])
                      ->whereBetween('lng', [$lon - 0.0001, $lon + 0.0001]);
                })->exists()) {
                $skipped++; continue;
            }

            // Extract alamat singkat (3 elemen terakhir)
            $addressParts = explode(',', $place['display_name'] ?? '');
            $shortAddress = trim(implode(',', array_slice($addressParts, -3))) ?: 'Yogyakarta';

            // Simpan ke database
            Content::create([
                'category_id'     => $category->id,
                'title'           => $name,
                'slug'            => \Str::slug($name) . '-' . \Str::random(4),
                'excerpt'         => $shortAddress,
                'content'         => "<p>{$name} adalah destinasi di Yogyakarta.</p>",
                'lat'             => $lat,
                'lng'             => $lon,
                'cover_image'     => null,       // Nanti diisi manual via Admin
                'opening_hours'   => null,
                'ticket_price'    => null,
                'phone'           => null,
                'website'         => null,
                'google_maps_url' => "https://www.google.com/maps/search/?api=1&query={$lat},{$lon}",
                'rating'          => null,       // Nanti diisi manual via Admin
                'review_count'    => 0,
                'is_featured'     => false,
                'status'          => 'published',
            ]);

            $saved++;
            $this->line("{$saved}. {$name}");

            // Nominatim rate limit: 1 request/detik (WAJIB)
            sleep(1);
        }

        $this->info("\n Selesai! Tersimpan: {$saved}, Dilewati: {$skipped}");
        $this->info("Foto & Rating bisa diisi manual nanti via Admin Dashboard.");
        return 0;
    }

    // Helper: Bersihkan nama dari aksara Jawa & karakter aneh
    private function cleanName(string $name): string
    {
        return trim(preg_replace('/[^\x20-\x7E]/u', '', $name));
    }
}