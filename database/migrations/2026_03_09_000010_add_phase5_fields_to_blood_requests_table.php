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
            $table->unsignedTinyInteger('quantity')->default(1)->after('blood_type');
            $table->enum('urgency_level', ['low', 'medium', 'high', 'critical'])->default('medium')->after('quantity');
        });

        DB::table('blood_requests')->update([
            'quantity' => DB::raw('COALESCE(requested_units, 1)'),
        ]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE blood_requests MODIFY status ENUM('pending', 'matching', 'completed', 'open', 'fulfilled', 'cancelled') NOT NULL DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE blood_requests MODIFY status ENUM('open', 'fulfilled', 'cancelled') NOT NULL DEFAULT 'open'");
        }

        Schema::table('blood_requests', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'urgency_level']);
        });
    }
};
