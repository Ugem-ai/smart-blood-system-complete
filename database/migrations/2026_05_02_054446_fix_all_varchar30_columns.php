<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blood_requests', function (Blueprint $table) {
            $table->string('component', 500)->nullable()->change();
            $table->string('contact_number', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('blood_requests', function (Blueprint $table) {
            $table->string('component', 30)->nullable()->change();
            $table->string('contact_number', 30)->nullable()->change();
        });
    }
};