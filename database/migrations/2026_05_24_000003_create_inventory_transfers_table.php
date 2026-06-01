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
        Schema::create('inventory_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_chapter_id')->constrained('chapters')->onDelete('cascade');
            $table->foreignId('destination_chapter_id')->constrained('chapters')->onDelete('cascade');
            $table->foreignId('blood_request_id')->nullable()->constrained('blood_requests')->onDelete('set null')->comment('Request that triggered this transfer');
            $table->string('blood_type')->comment('Blood type being transferred');
            $table->string('component_type')->comment('Component type');
            $table->integer('units_requested')->comment('Units requested for transfer');
            $table->integer('units_approved')->nullable()->comment('Units approved for transfer');
            $table->integer('units_transferred')->nullable()->comment('Units actually transferred');
            $table->date('expiration_date')->nullable();
            $table->enum('transfer_status', [
                'pending',
                'approved',
                'rejected',
                'in_transit',
                'completed',
                'cancelled',
                'expired_in_transit',
            ])->default('pending');
            $table->enum('priority_level', ['routine', 'urgent', 'emergency'])->default('routine');
            $table->text('reason_for_transfer')->nullable()->comment('Reason for requesting transfer');
            $table->text('rejection_reason')->nullable()->comment('If rejected, reason for rejection');
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('restrict');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('source_chapter_id');
            $table->index('destination_chapter_id');
            $table->index('transfer_status');
            $table->index('priority_level');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transfers');
    }
};
