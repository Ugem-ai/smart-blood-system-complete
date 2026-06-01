<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('donors')) {
            return;
        }

        Schema::table('donors', function (Blueprint $table) {
            $table->boolean('willing_for_emergency_travel')->default(false)->after('availability');
            $table->unsignedSmallInteger('normal_travel_radius')->default(5)->after('willing_for_emergency_travel');
            $table->unsignedSmallInteger('emergency_travel_radius')->nullable()->after('normal_travel_radius');
            $table->string('preferred_prc_chapter')->nullable()->after('emergency_travel_radius');
            $table->string('availability_status')->nullable()->after('preferred_prc_chapter');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('donors')) {
            return;
        }

        Schema::table('donors', function (Blueprint $table) {
            $table->dropColumn([
                'willing_for_emergency_travel',
                'normal_travel_radius',
                'emergency_travel_radius',
                'preferred_prc_chapter',
                'availability_status',
            ]);
        });
    }
};
