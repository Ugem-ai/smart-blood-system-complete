<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('blood_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('units_required')->default(1)->after('urgency_level');
            $table->decimal('latitude', 10, 7)->nullable()->after('status');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        DB::table('blood_requests')->update([
            'units_required' => DB::raw('COALESCE(quantity, requested_units, 1)'),
        ]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE blood_requests SET status = 'pending' WHERE status = 'open'");
            DB::statement("UPDATE blood_requests SET status = 'completed' WHERE status = 'fulfilled'");
            DB::statement("ALTER TABLE blood_requests MODIFY status ENUM('pending', 'matching', 'completed', 'cancelled') NOT NULL DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE blood_requests MODIFY status ENUM('pending', 'matching', 'completed', 'open', 'fulfilled', 'cancelled') NOT NULL DEFAULT 'pending'");
        }

        Schema::table('blood_requests', function (Blueprint $table) {
            $table->dropColumn(['units_required', 'latitude', 'longitude']);
        });
    }
};
