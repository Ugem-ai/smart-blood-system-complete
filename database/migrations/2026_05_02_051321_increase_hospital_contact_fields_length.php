<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hospitals', function (Blueprint $table) {
            $table->string('contact_number', 500)->change();
            $table->string('contact_person', 500)->change();
            $table->string('password', 500)->change();
            $table->string('location', 500)->change();
            $table->string('email', 500)->change();
        });
    }

    public function down(): void
    {
        Schema::table('hospitals', function (Blueprint $table) {
            $table->string('contact_number', 30)->change();
            $table->string('contact_person', 255)->change();
            $table->string('password', 255)->change();
            $table->string('location', 255)->change();
            $table->string('email', 255)->change();
        });
    }
};