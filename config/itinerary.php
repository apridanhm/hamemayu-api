<?php

return [
    // Estimasi durasi kunjungan per kategori (dalam jam)
    'duration_hours' => [
        'kuliner' => 1.5,
        'sejarah' => 2.5,
        'alam'    => 3.0,
        'budaya'  => 2.0,
        'default' => 2.0,
    ],

    // Waktu terbaik berkunjung per kategori
    'best_time' => [
        'kuliner' => ['siang', 'malam'],
        'sejarah' => ['pagi'],
        'alam'    => ['pagi', 'sore'],
        'budaya'  => ['pagi', 'sore'],
        'default' => ['pagi'],
    ],

    // Multiplier budget
    'budget_multiplier' => [
        'hemat'      => 0.7,
        'menengah'   => 1.0,
        'premium'    => 1.5,
    ],

    // Rata-rata harga tiket/makan per orang (base price)
    'base_price' => 35000,
];