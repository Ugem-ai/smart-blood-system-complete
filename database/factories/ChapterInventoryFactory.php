<?php

namespace Database\Factories;

use App\Models\Chapter;
use App\Models\ChapterInventory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChapterInventory>
 */
class ChapterInventoryFactory extends Factory
{
    protected $model = ChapterInventory::class;

    public function definition(): array
    {
        $units = fake()->numberBetween(0, 20);

        return [
            'chapter_id' => Chapter::factory(),
            'blood_type' => fake()->randomElement(['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-']),
            'component_type' => fake()->randomElement(['Whole Blood', 'Red Cells', 'Plasma', 'Platelets']),
            'units_available' => $units,
            'status' => $units === 0 ? 'critical' : ($units <= 5 ? 'low' : 'adequate'),
            'last_synced_at' => now(),
        ];
    }
}
