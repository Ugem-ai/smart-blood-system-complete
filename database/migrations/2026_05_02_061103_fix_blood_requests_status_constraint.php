<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'pgsql') {
            // PostgreSQL: Use named constraint
            DB::statement("ALTER TABLE blood_requests DROP CONSTRAINT IF EXISTS blood_requests_status_check");
            DB::statement("ALTER TABLE blood_requests ADD CONSTRAINT blood_requests_status_check 
                CHECK (status::text = ANY (ARRAY['open', 'fulfilled', 'cancelled', 'matching', 'completed', 'expired']))");
        } elseif ($driver === 'sqlite') {
            // SQLite: Just ensure the column exists (SQLite doesn't support named constraints easily)
            // The valid values are handled by the application layer
            if (!Schema::hasColumn('blood_requests', 'status')) {
                Schema::table('blood_requests', function (Blueprint $table) {
                    $table->string('status')->default('open');
                });
            }
        } else {
            // MySQL: Use CHECK constraint
            DB::statement("ALTER TABLE blood_requests DROP INDEX IF EXISTS blood_requests_status_check");
            DB::statement("ALTER TABLE blood_requests ADD CONSTRAINT blood_requests_status_check 
                CHECK (status IN ('open', 'fulfilled', 'cancelled', 'matching', 'completed', 'expired'))");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE blood_requests DROP CONSTRAINT IF EXISTS blood_requests_status_check");
            DB::statement("ALTER TABLE blood_requests ADD CONSTRAINT blood_requests_status_check 
                CHECK (status::text = ANY (ARRAY['open', 'fulfilled', 'cancelled']))");
        }
        // SQLite and MySQL don't need explicit down for column existence check
    }
};