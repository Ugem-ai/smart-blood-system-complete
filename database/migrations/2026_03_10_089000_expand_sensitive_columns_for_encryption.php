<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE donors MODIFY contact_number TEXT NOT NULL');
            DB::statement('ALTER TABLE donors MODIFY phone TEXT NULL');

            DB::statement('ALTER TABLE hospitals MODIFY address TEXT NULL');
            DB::statement('ALTER TABLE hospitals MODIFY location TEXT NOT NULL');
            DB::statement('ALTER TABLE hospitals MODIFY contact_person TEXT NOT NULL');
            DB::statement('ALTER TABLE hospitals MODIFY contact_number TEXT NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE donors MODIFY contact_number VARCHAR(30) NOT NULL');
            DB::statement('ALTER TABLE donors MODIFY phone VARCHAR(30) NULL');

            DB::statement('ALTER TABLE hospitals MODIFY address VARCHAR(255) NULL');
            DB::statement('ALTER TABLE hospitals MODIFY location VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE hospitals MODIFY contact_person VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE hospitals MODIFY contact_number VARCHAR(30) NOT NULL');
        }
    }
};
