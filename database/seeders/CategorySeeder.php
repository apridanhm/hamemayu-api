<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Sejarah',
                'slug' => 'sejarah',
                'type' => 'pilar',
                'order' => 1,
            ],
            [
                'name' => 'Budaya',
                'slug' => 'budaya',
                'type' => 'pilar',
                'order' => 2,
            ],
            [
                'name' => 'Kuliner',
                'slug' => 'kuliner',
                'type' => 'pilar',
                'order' => 3,
            ],
            [
                'name' => 'Wisata',
                'slug' => 'wisata',
                'type' => 'pilar',
                'order' => 4,
            ],
            [
                'name' => 'Teknologi',
                'slug' => 'teknologi',
                'type' => 'pilar',
                'order' => 5,
            ],
            [
                'name' => 'Peta',
                'slug' => 'peta',
                'type' => 'pilar',
                'order' => 6,
            ],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['slug' => $category['slug']], $category);
        }
    }
}