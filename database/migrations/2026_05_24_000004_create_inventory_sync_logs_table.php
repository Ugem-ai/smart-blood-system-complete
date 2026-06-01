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
        Schema::create('inventory_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained('chapters')->onDelete('cascade');
            $table->string('sync_action')->comment('Type of sync action (create, update, reserve, release, transfer, expire)');
            $table->string('blood_type')->nullable();
            $table->string('component_type')->nullable();
            $table->integer('units_changed')->nullable()->comment('Number of units affected');
            $table->enum('sync_status', [
                'pending',
                'in_progress',
                'completed',
                'failed',
                'rolled_back',
            ])->default('pending');
            $table->json('previous_state')->nullable()->comment('Previous inventory state before change');
            $table->json('new_state')->nullable()->comment('New inventory state after change');
            $table->foreignId('triggered_by_request_id')->nullable()->constrained('blood_requests')->onDelete('set null');
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('error_message')->nullable()->comment('If failed, error details');
            $table->integer('affected_chapters_count')->default(1)->comment('How many chapters were affected by this sync');
            $table->text('notes')->nullable();
            $table->timestamp('synced_at')->useCurrent();
            $table->timestamps();

            $table->index('chapter_id');
            $table->index('sync_action');
            $table->index('sync_status');
            $table->index('synced_at');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_sync_logs');
    }
};
