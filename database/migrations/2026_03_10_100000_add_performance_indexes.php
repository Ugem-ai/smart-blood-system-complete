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
        Schema::table('donors', function (Blueprint $table) {
            $table->index(['blood_type', 'availability'], 'donors_blood_availability_idx');
            $table->index('last_donation_date', 'donors_last_donation_idx');
            $table->index(['latitude', 'longitude'], 'donors_lat_lon_idx');
        });

        Schema::table('blood_requests', function (Blueprint $table) {
            $table->index(['hospital_id', 'status'], 'blood_requests_hospital_status_idx');
            $table->index(['created_at'], 'blood_requests_created_at_idx');
            $table->index(['blood_type', 'status'], 'blood_requests_blood_status_idx');
        });

        Schema::table('donor_request_responses', function (Blueprint $table) {
            $table->index(['blood_request_id', 'response'], 'drr_request_response_idx');
            $table->index('responded_at', 'drr_responded_at_idx');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->index(['response_status', 'score'], 'matches_response_score_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donors', function (Blueprint $table) {
            $table->dropIndex('donors_blood_availability_idx');
            $table->dropIndex('donors_last_donation_idx');
            $table->dropIndex('donors_lat_lon_idx');
        });

        Schema::table('blood_requests', function (Blueprint $table) {
            $table->dropIndex('blood_requests_hospital_status_idx');
            $table->dropIndex('blood_requests_created_at_idx');
            $table->dropIndex('blood_requests_blood_status_idx');
        });

        Schema::table('donor_request_responses', function (Blueprint $table) {
            $table->dropIndex('drr_request_response_idx');
            $table->dropIndex('drr_responded_at_idx');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex('matches_response_score_idx');
        });
    }
};
