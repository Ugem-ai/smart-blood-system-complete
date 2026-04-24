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
        Schema::table('donors', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('contact_number');
            $table->decimal('latitude', 10, 7)->nullable()->after('phone');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->decimal('reliability_score', 5, 2)->default(0)->after('availability');
        });

        // Backfill phone from existing contact_number for current records.
        DB::table('donors')->whereNull('phone')->update(['phone' => DB::raw('contact_number')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donors', function (Blueprint $table) {
            $table->dropColumn(['phone', 'latitude', 'longitude', 'reliability_score']);
        });
    }
};
