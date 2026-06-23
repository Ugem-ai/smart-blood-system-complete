<?php

namespace Database\Factories;

use App\Models\Chapter;
use App\Models\ChapterTransferRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChapterTransferRequest>
 */
class ChapterTransferRequestFactory extends Factory
{
    protected $model = ChapterTransferRequest::class;

    public function definition(): array
    {
        return [
            'source_chapter_id' => Chapter::factory(),
            'destination_chapter_id' => Chapter::factory(),
            'blood_type' => fake()->randomElement(['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-']),
            'component_type' => fake()->randomElement(['Whole Blood', 'Red Cells', 'Plasma', 'Platelets']),
            'units_requested' => fake()->numberBetween(1, 10),
            'priority' => fake()->randomElement(['routine', 'urgent', 'emergency']),
            'reason' => fake()->sentence(),
            'status' => 'pending',
        ];
    }
}
