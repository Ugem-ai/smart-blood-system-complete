<?php

namespace Database\Factories;

use App\Models\Chapter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Chapter>
 */
class ChapterFactory extends Factory
{
    protected $model = Chapter::class;

    public function definition(): array
    {
        $city = fake()->city();
        $name = 'PRC '.$city.' Chapter';

        return [
            'name' => $name,
            'chapter_name' => $name,
            'chapter_code' => 'CH-'.Str::upper(Str::random(8)),
            'location' => $city,
            'address' => fake()->address(),
            'region' => 'NCR',
            'province' => fake()->state(),
            'city' => $city,
            'latitude' => fake()->latitude(4.0, 19.0),
            'longitude' => fake()->longitude(116.0, 127.0),
            'is_active' => true,
            'status' => 'active',
            'capacity_units' => 500,
            'synced_at' => now(),
        ];
    }
}
