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
            // Drop existing constraint if it exists
            DB::statement("ALTER TABLE blood_requests DROP CONSTRAINT IF EXISTS blood_requests_status_check");

            // Re-add with updated values
            DB::statement("
                ALTER TABLE blood_requests 
                ADD CONSTRAINT blood_requests_status_check 
                CHECK (status IN ('open','pending','matching','matched','confirmed','completed','fulfilled','cancelled','expired'))
            ");

        } elseif ($driver === 'sqlite') {
            if (!Schema::hasColumn('blood_requests', 'status')) {
                Schema::table('blood_requests', function (Blueprint $table) {
                    $table->string('status')->default('open');
                });
            }

        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL 8.0.16+ supports CHECK constraints
            // Safely drop if exists using information_schema
            DB::statement("
                SET @constraint_exists = (
                    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                    WHERE CONSTRAINT_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'blood_requests'
                    AND CONSTRAINT_NAME = 'blood_requests_status_check'
                )
            ");
            DB::statement("
                SET @sql = IF(@constraint_exists > 0,
                    'ALTER TABLE blood_requests DROP CONSTRAINT blood_requests_status_check',
                    'SELECT 1'
                )
            ");
            DB::statement("PREPARE stmt FROM @sql");
            DB::statement("EXECUTE stmt");
            DB::statement("DEALLOCATE PREPARE stmt");

            DB::statement("
                ALTER TABLE blood_requests 
                ADD CONSTRAINT blood_requests_status_check 
                CHECK (status IN ('open','pending','matching','matched','confirmed','completed','fulfilled','cancelled','expired'))
            ");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE blood_requests DROP CONSTRAINT IF EXISTS blood_requests_status_check");
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE blood_requests DROP CONSTRAINT IF EXISTS blood_requests_status_check");
        }
        // SQLite: no action needed
    }
};