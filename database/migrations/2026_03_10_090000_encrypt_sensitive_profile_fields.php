<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $encryptIfNeeded = function (?string $value): ?string {
            if ($value === null || $value === '') {
                return $value;
            }

            try {
                Crypt::decryptString($value);

                // Already encrypted.
                return $value;
            } catch (Throwable) {
                return Crypt::encryptString($value);
            }
        };

        DB::table('donors')->orderBy('id')->get()->each(function ($row) use ($encryptIfNeeded) {
            DB::table('donors')->where('id', $row->id)->update([
                'contact_number' => $encryptIfNeeded($row->contact_number),
                'phone' => $encryptIfNeeded($row->phone),
            ]);
        });

        DB::table('hospitals')->orderBy('id')->get()->each(function ($row) use ($encryptIfNeeded) {
            DB::table('hospitals')->where('id', $row->id)->update([
                'address' => $encryptIfNeeded($row->address),
                'location' => $encryptIfNeeded($row->location),
                'contact_person' => $encryptIfNeeded($row->contact_person),
                'contact_number' => $encryptIfNeeded($row->contact_number),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible by design to avoid writing decrypted PII back to storage.
    }
};
