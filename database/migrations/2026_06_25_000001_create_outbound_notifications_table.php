<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('outbound_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('to');
            $table->string('channel')->default('sms');
            $table->json('payload')->nullable();
            $table->string('status')->default('pending');
            $table->integer('attempts')->default(0);
            $table->string('provider')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('outbound_notifications');
    }
};
