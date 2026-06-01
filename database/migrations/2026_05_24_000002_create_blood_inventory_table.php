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
        Schema::create('blood_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->nullable()->constrained('chapters')->nullOnDelete();
            $table->foreignId('hospital_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('blood_type')->comment('Blood type (A+, A-, B+, B-, AB+, AB-, O+, O-)');
            $table->string('component_type')->nullable()->comment('Component type (Whole Blood, RBC, Plasma, Platelets, etc.)');
            $table->integer('units_available')->default(0)->comment('Current available units');
            $table->integer('units_reserved')->default(0)->comment('Units reserved for requests');
            $table->timestamp('last_updated')->nullable()->comment('Last hospital inventory update timestamp');
            $table->integer('units_in_transit')->default(0)->comment('Units in transit from other chapters');
            $table->date('expiration_date')->nullable()->comment('Expiration date of blood units');
            $table->enum('inventory_status', [
                'available',
                'reserved',
                'in_transit',
                'used',
                'expired',
                'quarantined',
                'damaged',
            ])->default('available')->comment('Current status of inventory');
            $table->foreignId('reserved_for_request_id')->nullable()->constrained('blood_requests')->onDelete('set null');
            $table->integer('critical_level')->default(2)->comment('Critical stock level threshold');
            $table->integer('reorder_level')->default(5)->comment('Reorder point threshold');
            $table->text('notes')->nullable();
            $table->timestamp('last_updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->timestamps();

            $table->index('chapter_id');
            $table->index('hospital_id');
            $table->index(['blood_type', 'component_type']);
            $table->index('inventory_status');
            $table->index('expiration_date');
            $table->unique(['chapter_id', 'blood_type', 'component_type', 'expiration_date']);
            $table->unique(['hospital_id', 'blood_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blood_inventory');
    }
};
