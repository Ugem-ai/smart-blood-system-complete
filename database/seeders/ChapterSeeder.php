<?php

namespace Database\Seeders;

use App\Models\Chapter;
use Illuminate\Database\Seeder;

class ChapterSeeder extends Seeder
{
    public function run(): void
    {
        $chapters = [
            [
                'name' => 'PRC Cavite Chapter',
                'chapter_name' => 'PRC Cavite Chapter',
                'chapter_code' => 'PRC-CAVITE',
                'location' => 'Dasmari\u00f1as, Cavite',
                'region' => 'Region IV-A',
                'province' => 'Cavite',
                'city' => 'Dasmari\u00f1as',
                'latitude' => 14.3296,
                'longitude' => 120.9367,
                'address' => 'Dasmari\u00f1as, Cavite',
                'is_active' => true,
                'status' => 'active',
                'synced_at' => now(),
            ],
            [
                'name' => 'PRC Manila Chapter',
                'chapter_name' => 'PRC Manila Chapter',
                'chapter_code' => 'PRC-MANILA',
                'location' => 'Manila City',
                'region' => 'NCR',
                'province' => 'Metro Manila',
                'city' => 'Manila',
                'latitude' => 14.5995,
                'longitude' => 120.9842,
                'address' => 'Manila City',
                'is_active' => true,
                'status' => 'active',
                'synced_at' => now(),
            ],
            [
                'name' => 'PRC Laguna Chapter',
                'chapter_name' => 'PRC Laguna Chapter',
                'chapter_code' => 'PRC-LAGUNA',
                'location' => 'Calamba, Laguna',
                'region' => 'Region IV-A',
                'province' => 'Laguna',
                'city' => 'Calamba',
                'latitude' => 14.2117,
                'longitude' => 121.1653,
                'address' => 'Calamba, Laguna',
                'is_active' => true,
                'status' => 'active',
                'synced_at' => now(),
            ],
        ];

        foreach ($chapters as $chapter) {
            Chapter::query()->updateOrCreate(
                ['chapter_code' => $chapter['chapter_code']],
                $chapter,
            );
        }
    }
}
