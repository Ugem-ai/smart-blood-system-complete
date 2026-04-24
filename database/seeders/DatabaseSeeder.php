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
        $admin = User::query()->updateOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $donor = User::query()->updateOrCreate([
            'email' => 'donor@example.com',
        ], [
            'name' => 'Demo Donor',
            'password' => Hash::make('password'),
            'role' => 'donor',
        ]);

        Donor::query()->updateOrCreate([
            'user_id' => $donor->id,
        ], [
            'name' => 'Demo Donor',
            'blood_type' => 'O+',
            'city' => 'Metro City',
            'contact_number' => '09170000001',
            'phone' => '09170000001',
            'email' => 'donor@example.com',
            'password' => 'password',
            'availability' => true,
            'reliability_score' => 95,
            'privacy_consent_at' => now(),
        ]);

        $hospitalUser = User::query()->updateOrCreate([
            'email' => 'hospital@example.com',
        ], [
            'name' => 'Demo Hospital',
            'password' => Hash::make('password'),
            'role' => 'hospital',
        ]);

        Hospital::query()->updateOrCreate([
            'user_id' => $hospitalUser->id,
        ], [
            'hospital_name' => 'City General Hospital',
            'address' => '123 Health Avenue',
            'location' => '123 Health Avenue',
            'contact_person' => 'Demo Hospital',
            'contact_number' => '09170000002',
            'email' => 'hospital@example.com',
            'password' => 'password',
            'status' => 'approved',
        ]);

        $this->call(AnalyticsDemoSeeder::class);
    }
}
