<?php

namespace Database\Seeders;

use App\Models\Donor;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedAdmin();
        $this->seedHospitalDemo();
        $this->seedDonorDemo();

        $this->call([
            ChapterSeeder::class,
            ChapterInventorySeeder::class,
            ChapterApiKeySeeder::class,
        ]);
    }

    private function seedAdmin(): void
    {
        User::query()->updateOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
    }

    private function seedHospitalDemo(): void
    {
        $user = User::query()->updateOrCreate([
            'email' => 'hospital@example.com',
        ], [
            'name' => 'Demo Hospital',
            'password' => Hash::make('password'),
            'role' => 'hospital',
        ]);

        Hospital::query()->updateOrCreate([
            'user_id' => $user->id,
        ], [
            'hospital_name' => 'Demo Hospital',
            'address' => '123 Demo Street',
            'location' => 'Demo City',
            'contact_person' => 'Hospital Admin',
            'contact_number' => '09170000000',
            'email' => 'hospital@example.com',
            'status' => 'approved',
        ]);
    }

    private function seedDonorDemo(): void
    {
        $user = User::query()->updateOrCreate([
            'email' => 'donor@example.com',
        ], [
            'name' => 'Demo Donor',
            'password' => Hash::make('password'),
            'role' => 'donor',
        ]);

        Donor::query()->updateOrCreate([
            'user_id' => $user->id,
        ], [
            'name' => 'Demo Donor',
            'blood_type' => 'O+',
            'city' => 'Demo City',
            'contact_number' => '09170000001',
            'phone' => '09170000001',
            'email' => 'donor@example.com',
            'password' => 'password',
            'availability' => true,
            'reliability_score' => 80,
            'privacy_consent_at' => now(),
        ]);
    }
}
