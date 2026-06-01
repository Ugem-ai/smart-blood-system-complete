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
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blood_inventory_id')->constrained('blood_inventory')->onDelete('cascade');
            $table->foreignId('chapter_id')->constrained('chapters')->onDelete('cascade');
            $table->enum('transaction_type', [
                'donation',
                'reserve',
                'release_reservation',
                'transfer_out',
                'transfer_in',
                'usage',
                'expiration',
                'quarantine',
                'release_quarantine',
                'destruction',
                'adjustment',
            ])->comment('Type of transaction');
            $table->integer('quantity_changed')->comment('Change in quantity (positive or negative)');
            $table->integer('quantity_before')->comment('Quantity before transaction');
            $table->integer('quantity_after')->comment('Quantity after transaction');
            $table->foreignId('blood_request_id')->nullable()->constrained('blood_requests')->onDelete('set null');
            $table->foreignId('inventory_transfer_id')->nullable()->constrained('inventory_transfers')->onDelete('set null');
            $table->foreignId('donor_id')->nullable()->constrained('donors')->onDelete('set null');
            $table->foreignId('performed_by_user_id')->constrained('users')->onDelete('restrict');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable()->comment('Additional transaction details');
            $table->timestamps();

            $table->index('chapter_id');
            $table->index('blood_inventory_id');
            $table->index('transaction_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
