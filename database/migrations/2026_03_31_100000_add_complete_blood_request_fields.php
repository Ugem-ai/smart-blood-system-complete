<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blood_requests', function (Blueprint $table) {
            // Patient / Case context
            $table->string('case_id', 32)->nullable()->unique()->after('id');
            $table->string('component', 30)->nullable()->after('blood_type');   // Whole Blood, PRBC, Platelets, Plasma
            $table->string('reason', 100)->nullable()->after('component');       // surgery, trauma, dengue …

            // Hospital contact override (can differ from profile)
            $table->string('contact_person', 150)->nullable()->after('hospital_name');
            $table->string('contact_number', 30)->nullable()->after('contact_person');

            // Extended location
            $table->string('province', 100)->nullable()->after('city');

            // Store the resolved search radius so the record is self-contained
            $table->decimal('distance_limit_km', 6, 2)->nullable()->after('longitude');

            // Time-constraint extension
            $table->timestamp('expiry_time')->nullable()->after('required_on');

            // Emergency flag (derived from urgency + disaster mode, storable explicitly)
            $table->boolean('is_emergency')->default(false)->after('status');

            // Matching / tracking counters (system-maintained, not user input)
            $table->unsignedSmallInteger('matched_donors_count')->default(0);
            $table->unsignedSmallInteger('notifications_sent')->default(0);
            $table->unsignedSmallInteger('responses_received')->default(0);
            $table->unsignedSmallInteger('accepted_donors')->default(0);
            $table->unsignedSmallInteger('fulfilled_units')->default(0);
        });

        // MySQL: extend enums to support 'critical' urgency and 'fulfilled' status
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE blood_requests
                 MODIFY urgency_level ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium'"
            );
            DB::statement(
                "ALTER TABLE blood_requests
                 MODIFY status ENUM('pending','matching','completed','fulfilled','cancelled') NOT NULL DEFAULT 'pending'"
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE blood_requests
                 MODIFY urgency_level ENUM('low','medium','high') NOT NULL DEFAULT 'medium'"
            );
            DB::statement(
                "ALTER TABLE blood_requests
                 MODIFY status ENUM('pending','matching','completed','cancelled') NOT NULL DEFAULT 'pending'"
            );
        }

        Schema::table('blood_requests', function (Blueprint $table) {
            $table->dropColumn([
                'case_id', 'component', 'reason',
                'contact_person', 'contact_number',
                'province', 'distance_limit_km', 'expiry_time',
                'is_emergency',
                'matched_donors_count', 'notifications_sent',
                'responses_received', 'accepted_donors', 'fulfilled_units',
            ]);
        });
    }
};
