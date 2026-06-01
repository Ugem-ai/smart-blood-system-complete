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
        Schema::create('chapters', function (Blueprint $table) {
            $table->id();
            $table->string('chapter_code')->unique()->comment('Unique identifier for PRC chapter (e.g., PRC_MNL_001)');
            $table->string('chapter_name')->comment('Name of the PRC chapter');
            $table->text('address')->nullable()->comment('Full address of the chapter');
            $table->decimal('latitude', 10, 8)->nullable()->comment('Geographic latitude');
            $table->decimal('longitude', 11, 8)->nullable()->comment('Geographic longitude');
            $table->string('contact_number')->nullable()->comment('Primary contact number');
            $table->string('email')->nullable()->comment('Chapter email');
            $table->string('region')->comment('Administrative region (NCR, Region I, etc.)');
            $table->string('province')->nullable()->comment('Province name');
            $table->string('city')->nullable()->comment('City/municipality');
            $table->enum('status', ['active', 'inactive', 'temporarily_closed'])->default('active');
            $table->integer('capacity_units')->default(500)->comment('Max blood units chapter can store');
            $table->text('notes')->nullable();
            $table->timestamp('synced_at')->nullable()->comment('Last sync timestamp for inter-chapter coordination');
            $table->timestamps();

            $table->index('chapter_code');
            $table->index('region');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chapters');
    }
};
