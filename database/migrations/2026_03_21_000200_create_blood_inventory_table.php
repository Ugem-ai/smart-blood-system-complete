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
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('blood_type', 5);
            $table->unsignedInteger('units_available')->default(0);
            $table->timestamp('last_updated');
            $table->timestamps();

            $table->unique(['hospital_id', 'blood_type']);
            $table->index(['blood_type', 'units_available']);
            $table->index(['hospital_id', 'last_updated']);
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
