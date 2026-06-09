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
            DB::statement("ALTER TABLE blood_requests DROP CONSTRAINT IF EXISTS blood_requests_status_check");
            DB::statement("ALTER TABLE blood_requests ADD CONSTRAINT blood_requests_status_check CHECK (status IN ('open','pending','matching','matched','confirmed','completed','fulfilled','cancelled','expired'))");
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE blood_requests DROP CONSTRAINT IF EXISTS blood_requests_status_check");
            DB::statement("ALTER TABLE blood_requests ADD CONSTRAINT blood_requests_status_check CHECK (status IN ('open','pending','matching','matched','confirmed','completed','fulfilled','cancelled','expired'))");
        } elseif ($driver === 'sqlite') {
            if (! Schema::hasColumn('blood_requests', 'status')) {
                Schema::table('blood_requests', function (Blueprint $table) {
                    $table->string('status')->default('open');
                });
            }
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
    }
};
