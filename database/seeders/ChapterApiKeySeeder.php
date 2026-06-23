<?php

namespace Database\Seeders;

use App\Models\Chapter;
use App\Models\ChapterApiKey;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ChapterApiKeySeeder extends Seeder
{
    public function run(): void
    {
        Chapter::query()->get()->each(function (Chapter $chapter): void {
            ChapterApiKey::query()->updateOrCreate(
                [
                    'chapter_id' => $chapter->id,
                    'label' => 'Default Key',
                    'is_active' => true,
                ],
                [
                    'api_key' => Str::random(64),
                    'last_used_at' => null,
                ],
            );
        });
    }
}
