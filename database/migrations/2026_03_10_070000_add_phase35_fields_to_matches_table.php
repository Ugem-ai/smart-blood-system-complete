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
        Schema::table('matches', function (Blueprint $table) {
            $table->foreignId('request_id')->nullable()->after('blood_request_id')->constrained('blood_requests')->cascadeOnDelete();
            $table->enum('response_status', ['pending', 'accepted', 'declined', 'expired'])->default('pending')->after('score');
        });

        DB::table('matches')->update([
            'request_id' => DB::raw('blood_request_id'),
        ]);

        Schema::table('matches', function (Blueprint $table) {
            $table->index(['request_id', 'response_status']);
            $table->unique(['request_id', 'donor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropUnique(['request_id', 'donor_id']);
            $table->dropIndex(['request_id', 'response_status']);
            $table->dropConstrainedForeignId('request_id');
            $table->dropColumn('response_status');
        });
    }
};
