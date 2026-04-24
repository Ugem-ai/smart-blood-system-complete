<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('blood_requests', function (Blueprint $table) {
            $table->foreignId('hospital_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->json('matched_donors')->nullable()->after('status');
            $table->timestamp('donor_assignment_confirmed_at')->nullable()->after('matched_donors');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blood_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hospital_id');
            $table->dropColumn(['matched_donors', 'donor_assignment_confirmed_at']);
        });
    }
};
