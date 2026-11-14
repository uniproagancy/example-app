<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abandoned_carts', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->json('items');
            $table->json('lines');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->string('locale', 5)->default('ka');
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abandoned_carts');
    }
};
