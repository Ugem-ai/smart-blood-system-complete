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
        Schema::create('donor_alert_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blood_request_id')->constrained('blood_requests')->cascadeOnDelete();
            $table->foreignId('donor_id')->constrained('donors')->cascadeOnDelete();
            $table->unsignedTinyInteger('escalation_level')->default(1);
            $table->string('channel', 20)->default('multi');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['blood_request_id', 'donor_id', 'escalation_level'], 'donor_alert_unique_per_level');
            $table->index(['donor_id', 'sent_at']);
            $table->index(['blood_request_id', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donor_alert_logs');
    }
};
