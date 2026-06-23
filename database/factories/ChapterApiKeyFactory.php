<?php

namespace Database\Factories;

use App\Models\Chapter;
use App\Models\ChapterApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChapterApiKey>
 */
class ChapterApiKeyFactory extends Factory
{
    protected $model = ChapterApiKey::class;

    public function definition(): array
    {
        return [
            'chapter_id' => Chapter::factory(),
            'api_key' => Str::random(64),
            'label' => 'Default Key',
            'last_used_at' => null,
            'is_active' => true,
        ];
    }
}
