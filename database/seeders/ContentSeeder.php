<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Content;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $sejarah = Category::where('slug', 'sejarah')->first();
        $budaya = Category::where('slug', 'budaya')->first();
        $kuliner = Category::where('slug', 'kuliner')->first();
        $wisata = Category::where('slug', 'wisata')->first();

        $contents = [
            // 1. Keraton Yogyakarta
            [
                'category_id' => $sejarah->id,
                'title' => 'Keraton Yogyakarta',
                'slug' => 'keraton-yogyakarta',
                'excerpt' => 'Istana resmi Kesultanan Yogyakarta yang berdiri sejak 1756. Pusat budaya Jawa dengan arsitektur tradisional dan koleksi benda bersejarah.',
                'content' => '<p>Keraton Yogyakarta (Keraton Ngayogyakarta Hadiningrat) didirikan oleh Sultan Hamengkubuwono I pada tahun 1755-1756 sebagai pusat pemerintahan dan budaya Kesultanan Yogyakarta.</p><p>Keraton ini merupakan kompleks bangunan tradisional Jawa yang megah dengan arsitektur perpaduan Jawa, Belanda, dan Tiongkok. Di dalamnya tersimpan berbagai koleksi benda bersejarah, pusaka kerajaan, dan karya seni.</p><p><strong>Filosofi:</strong> Keraton melambangkan harmoni antara manusia, alam, dan Tuhan (Hamemayu Hayuning Bawana).</p>',
                'lat' => -7.8053,
                'lng' => 110.3644,
                'opening_hours' => '08:00 - 14:00 (Selasa-Minggu)',
                'ticket_price' => 'Rp 15.000 - Rp 50.000',
                'cover_image' => 'https://images.unsplash.com/photo-1596401057633-565652b50f4e?w=800',
                'is_featured' => true,
                'status' => 'published',
                'street_view_id' => '!1m2!1s0x2e7a8b3c4d5e6f7:0x1234567890abcdef!2m1!1sKeraton+Yogyakarta',
                'google_maps_url' => 'https://maps.app.goo.gl/keraton-yogya',
            ],
            // 2. Candi Prambanan
            [
                'category_id' => $sejarah->id,
                'title' => 'Candi Prambanan',
                'slug' => 'candi-prambanan',
                'excerpt' => 'Kompleks candi Hindu terbesar di Indonesia, dibangun abad ke-9 dengan arsitektur megah dan relief Ramayana.',
                'content' => '<p>Candi Prambanan atau Candi Rara Jonggrang adalah kompleks candi Hindu terbesar di Indonesia yang dibangun sekitar tahun 850 M oleh Rakai Pikatan, raja kedua wangsa Mataram I.</p><p>Terdiri dari 240 candi dengan 3 candi utama: Candi Siwa (47m), Candi Wisnu, dan Candi Brahma. Relief Ramayana menghiasi dinding candi, menceritakan kisah epik Ramayana.</p><p><strong>UNESCO World Heritage Site</strong> sejak 1991.</p>',
                'lat' => -7.7520,
                'lng' => 110.4913,
                'opening_hours' => '06:00 - 17:30',
                'ticket_price' => 'Rp 50.000 (Wisnus), $25 (Mancanegara)',
                'cover_image' => 'https://images.unsplash.com/photo-1555400038-63f5ba517a47?w=800',
                'is_featured' => true,
                'status' => 'published',
                'street_view_id' => '!1m2!1s0x2e7a8b3c4d5e6f7:0x1234567890abcdef!2m1!1sKeraton+Yogyakarta',
                'google_maps_url' => 'https://maps.app.goo.gl/keraton-yogya',
            ],
            // 3. Taman Sari
            [
                'category_id' => $sejarah->id,
                'title' => 'Taman Sari Water Castle',
                'slug' => 'taman-sari',
                'excerpt' => 'Istana air bersejarah dengan arsitektur unik perpaduan Jawa-Eropa, bekas tempat rekreasi keluarga keraton.',
                'content' => '<p>Taman Sari dibangun tahun 1758-1765 oleh Sultan Hamengkubuwono I, dirancang oleh arsitek Portugis Demang Tegis. Kompleks ini pernah berfungsi sebagai benteng, tempat meditasi, pemandian, dan taman rekreasi.</p><p>Arsitekturnya unik dengan perpaduan gaya Jawa, Portugis, dan Belanda. Terdapat kolam pemandian, sumur jalatunda, dan ruang bawah tanah.</p>',
                'lat' => -7.8122,
                'lng' => 110.3589,
                'opening_hours' => '08:00 - 16:00',
                'ticket_price' => 'Rp 10.000 - Rp 15.000',
                'cover_image' => 'https://images.unsplash.com/photo-1583531254560-6468b3aa596c?w=800',
                'is_featured' => false,
                'status' => 'published',
                'street_view_id' => '!1m2!1s0x2e7a8b3c4d5e6f7:0x1234567890abcdef!2m1!1sKeraton+Yogyakarta',
                'google_maps_url' => 'https://maps.app.goo.gl/keraton-yogya',
            ],
            // 4. Batik Yogyakarta
            [
                'category_id' => $budaya->id,
                'title' => 'Batik Yogyakarta',
                'slug' => 'batik-yogyakarta',
                'excerpt' => 'Warisan budaya tak benda UNESCO dengan motif klasik seperti Parang, Kawung, dan Semen yang sarat filosofi Jawa.',
                'content' => '<p>Batik Yogyakarta memiliki motif khas yang sarat makna filosofis. Motif Parang melambangkan kesinambungan, Kawung melambangkan kesempurnaan, dan Semen melambangkan kehidupan.</p><p>Proses pembuatan batik tulis tradisional masih dilestarikan hingga kini, menggunakan canting dan malam (lilin batik) dengan teknik yang diwariskan turun-temurun.</p><p><strong>UNESCO Intangible Cultural Heritage</strong> sejak 2009.</p>',
                'lat' => -7.8067,
                'lng' => 110.3656,
                'opening_hours' => '09:00 - 17:00',
                'ticket_price' => 'Gratis (beli produk mulai Rp 50.000)',
                'cover_image' => 'https://images.unsplash.com/photo-1610189012906-4f2e7910e6b8?w=800',
                'is_featured' => false,
                'status' => 'published',
                'street_view_id' => '!1m2!1s0x2e7a8b3c4d5e6f7:0x1234567890abcdef!2m1!1sKeraton+Yogyakarta',
                'google_maps_url' => 'https://maps.app.goo.gl/keraton-yogya',
            ],
            // 5. Gudeg Yuwono
            [
                'category_id' => $kuliner->id,
                'title' => 'Gudeg Yuwono',
                'slug' => 'gudeg-yuwono',
                'excerpt' => 'Gudeg legendaris Yogyakarta dengan cita rasa manis khas, buka 24 jam. Rekomendasi utama untuk pengalaman gudeg autentik.',
                'content' => '<p>Gudeg Yuwono berdiri sejak 1960-an, menjadi salah satu pelopor gudeg kaleng pertama di Yogyakarta. Gudeg dibuat dari nangka muda yang dimasak dengan santan dan gula merah selama berjam-jam.</p><p><strong>Paket Komplit:</strong> Nasi gudeg, ayam kampung, telur bacem, krecek, sambal goreng ati, dan kuah areh.</p><p>Tersedia gudeg kaleng untuk oleh-oleh (tahan 1-2 minggu).</p>',
                'lat' => -7.7833,
                'lng' => 110.3833,
                'opening_hours' => '24 jam',
                'ticket_price' => 'Rp 15.000 - Rp 35.000',
                'cover_image' => 'https://images.unsplash.com/photo-1565557623262-b51c2513a641?w=800',
                'is_featured' => true,
                'status' => 'published',
                'street_view_id' => '!1m2!1s0x2e7a8b3c4d5e6f7:0x1234567890abcdef!2m1!1sKeraton+Yogyakarta',
                'google_maps_url' => 'https://maps.app.goo.gl/keraton-yogya',
            ],
            // 6. Angkringan Lik Man
            [
                'category_id' => $kuliner->id,
                'title' => 'Angkringan Lik Man',
                'slug' => 'angkringan-lik-man',
                'excerpt' => 'Angkringan legendaris Malioboro dengan nasi kucing, sate, dan wedang jahe. Tempat nongkrong autentik rakyat Yogyakarta.',
                'content' => '<p>Angkringan adalah budaya kuliner jalanan Yogyakarta yang muncul sejak 1950-an. Lik Man menjadi salah satu angkringan paling ikonik di Malioboro.</p><p><strong>Menu Andalan:</strong> Nasi kucing (nasi bungkus kecil dengan sambal, ikan teri, telur), sate ampela, wedang jahe, dan kopi joss (kopi dengan arang panas).</p><p>Harga terjangkau (Rp 5.000 - Rp 20.000), suasana hangat, cocok untuk nongkrong malam.</p>',
                'lat' => -7.7900,
                'lng' => 110.3630,
                'opening_hours' => '18:00 - 02:00',
                'ticket_price' => 'Rp 5.000 - Rp 20.000',
                'cover_image' => 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=800',
                'is_featured' => false,
                'status' => 'published',
                'street_view_id' => '!1m2!1s0x2e7a8b3c4d5e6f7:0x1234567890abcdef!2m1!1sKeraton+Yogyakarta',
                'google_maps_url' => 'https://maps.app.goo.gl/keraton-yogya',
            ],
            // 7. Pantai Parangtritis
            [
                'category_id' => $wisata->id,
                'title' => 'Pantai Parangtritis',
                'slug' => 'pantai-parangtritis',
                'excerpt' => 'Pantai ikonik Yogyakarta dengan pasir hitam, ombak besar, dan legenda Nyi Roro Kidul. Spot sunset terbaik.',
                'content' => '<p>Pantai Parangtritis adalah destinasi wisata paling ikonik di Yogyakarta. Terkenal dengan pasir hitam vulkanik, ombak besar, dan legenda mistis Nyi Roro Kidul (Ratu Laut Selatan).</p><p><strong>Aktivitas:</strong> Sunset viewing, horse carriage (andong), ATV, flying fox, dan mengunjungi gumuk pasir (sand dunes).</p><p><strong>Peringatan:</strong> Dilarang berenang di laut karena ombak besar dan arus bawah yang berbahaya.</p>',
                'lat' => -8.0583,
                'lng' => 110.3283,
                'opening_hours' => '24 jam',
                'ticket_price' => 'Rp 10.000 (weekday), Rp 15.000 (weekend)',
                'cover_image' => 'https://images.unsplash.com/photo-1582510003544-4d00b7f74220?w=800',
                'is_featured' => true,
                'status' => 'published',
                'street_view_id' => '!1m2!1s0x2e7a8b3c4d5e6f7:0x1234567890abcdef!2m1!1sKeraton+Yogyakarta',
                'google_maps_url' => 'https://maps.app.goo.gl/keraton-yogya',
            ],
            // 8. Malioboro
            [
                'category_id' => $wisata->id,
                'title' => 'Jalan Malioboro',
                'slug' => 'jalan-malioboro',
                'excerpt' => 'Icon street Yogyakarta dengan shopping, kuliner, dan budaya. Pusat aktivitas 24 jam.',
                'content' => '<p>Jalan Malioboro adalah jantung kota Yogyakarta. Nama "Malioboro" berasal dari bahasa Sanskerta "malabara" atau dari nama perwira Inggris Marlborough.</p><p><strong>Aktivitas:</strong> Belanja batik & kerajinan, naik andong/becak, kuliner malam di lesehan, street performance, dan merasakan suasana kota yang hidup 24 jam.</p><p>Destinasi wajib untuk merasakan "jiwa" Yogyakarta.</p>',
                'lat' => -7.7928,
                'lng' => 110.3647,
                'opening_hours' => '24 jam',
                'ticket_price' => 'Gratis',
                'cover_image' => 'https://images.unsplash.com/photo-1537956965359-7573183d1f57?w=800',
                'is_featured' => false,
                'status' => 'published',
                'street_view_id' => '!1m2!1s0x2e7a8b3c4d5e6f7:0x1234567890abcdef!2m1!1sKeraton+Yogyakarta',
                'google_maps_url' => 'https://maps.app.goo.gl/keraton-yogya',
            ],
        ];

        foreach ($contents as $content) {
            Content::firstOrCreate(['slug' => $content['slug']], $content);
        }
    }
}