<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE blood_requests DROP CONSTRAINT IF EXISTS blood_requests_status_check");
        DB::statement("ALTER TABLE blood_requests ADD CONSTRAINT blood_requests_status_check 
            CHECK (status::text = ANY (ARRAY['open', 'fulfilled', 'cancelled', 'matching', 'completed', 'expired']))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE blood_requests DROP CONSTRAINT IF EXISTS blood_requests_status_check");
        DB::statement("ALTER TABLE blood_requests ADD CONSTRAINT blood_requests_status_check 
            CHECK (status::text = ANY (ARRAY['open', 'fulfilled', 'cancelled']))");
    }
};