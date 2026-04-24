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
        Schema::table('donation_histories', function (Blueprint $table) {
            $table->foreignId('hospital_id')->nullable()->after('donor_id')->constrained()->nullOnDelete();
            $table->foreignId('request_id')->nullable()->after('hospital_id')->constrained('blood_requests')->nullOnDelete();
            $table->date('donation_date')->nullable()->after('donated_at');
            $table->string('status')->default('pending')->after('units');
        });

        DB::table('donation_histories')->update([
            'donation_date' => DB::raw('DATE(donated_at)'),
            'status' => DB::raw("COALESCE(status, 'completed')"),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donation_histories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hospital_id');
            $table->dropConstrainedForeignId('request_id');
            $table->dropColumn(['donation_date', 'status']);
        });
    }
};
