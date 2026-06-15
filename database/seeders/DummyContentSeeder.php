<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Content;
use Illuminate\Database\Seeder;

class DummyContentSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Pastikan kategori 'kuliner' ada (hanya name & slug)
        $kuliner = Category::firstOrCreate(
            ['slug' => 'kuliner'],
            ['name' => 'Kuliner']
        );

        // 2. Data dummy destinasi
        $contents = [
            [
                'title' => 'Gudeg Yuwono',
                'slug' => 'gudeg-yuwono',
                'category_id' => $kuliner->id,
                'excerpt' => 'Gudeg legendaris Jogja sejak 1960-an.',
                'content' => '<p>Gudeg Yuwono adalah salah satu gudeg paling terkenal di Yogyakarta...</p>',
                'lat' => -7.7833,
                'lng' => 110.3833,
                'opening_hours' => '08:00 - 21:00',
                'ticket_price' => 'Mulai Rp 15.000',
                'cover_image' => 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=500',
                'status' => 'published',
                'is_featured' => true,
            ],
            [
                'title' => 'Angkringan Lik Man',
                'slug' => 'angkringan-lik-man',
                'category_id' => $kuliner->id,
                'excerpt' => 'Angkringan legendaris dekat Tugu Jogja.',
                'content' => '<p>Angkringan Lik Man terkenal dengan nasi kucing dan kopi joss-nya...</p>',
                'lat' => -7.7900,
                'lng' => 110.3630,
                'opening_hours' => '18:00 - 02:00',
                'ticket_price' => 'Mulai Rp 5.000',
                'cover_image' => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=500',
                'status' => 'published',
                'is_featured' => false,
            ],
            [
                'title' => 'Bakpia Pathok 25',
                'slug' => 'bakpia-pathok-25',
                'category_id' => $kuliner->id,
                'excerpt' => 'Oleh-oleh khas Jogja yang wajib dibawa pulang.',
                'content' => '<p>Bakpia Pathok 25 memproduksi bakpia dengan berbagai varian rasa...</p>',
                'lat' => -7.7889,
                'lng' => 110.3656,
                'opening_hours' => '08:00 - 21:00',
                'ticket_price' => 'Mulai Rp 25.000/box',
                'cover_image' => 'https://images.unsplash.com/photo-1626082927686-0c3f6c8f5c8e?w=500',
                'status' => 'published',
                'is_featured' => false,
            ],
        ];

        // 3. Insert ke database
        foreach ($contents as $content) {
            Content::firstOrCreate(['slug' => $content['slug']], $content);
        }
    }
}