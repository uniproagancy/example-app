<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_pixel_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('event_id', 64);
            $table->string('fbp')->nullable();
            $table->string('fbc')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->boolean('consented')->default(false);
            $table->string('external_id', 191)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->string('action_source', 32)->nullable();
            $table->json('custom_data')->nullable();
            $table->json('user_data')->nullable();
            $table->timestamp('browser_sent_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_pixel_data');
    }
};
