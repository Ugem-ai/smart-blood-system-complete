<?php

namespace Database\Seeders;

use App\Models\Chapter;
use App\Models\ChapterInventory;
use Illuminate\Database\Seeder;

class ChapterInventorySeeder extends Seeder
{
    public function run(): void
    {
        $bloodTypes = ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'];

        Chapter::query()->get()->each(function (Chapter $chapter) use ($bloodTypes): void {
            foreach ($bloodTypes as $bloodType) {
                $units = random_int(0, 20);

                ChapterInventory::query()->updateOrCreate(
                    [
                        'chapter_id' => $chapter->id,
                        'blood_type' => $bloodType,
                        'component_type' => 'Whole Blood',
                    ],
                    [
                        'units_available' => $units,
                        'status' => $units === 0 ? 'critical' : ($units <= 5 ? 'low' : 'adequate'),
                        'last_synced_at' => now(),
                    ],
                );
            }
        });
    }
}
