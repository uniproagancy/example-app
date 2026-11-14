<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contact');
            $table->string('channel', 10);
            $table->decimal('price', 10, 2);
            $table->decimal('notified_price', 10, 2)->nullable();
            $table->string('locale', 5)->default('ka');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'contact']);
            $table->index('notified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_alerts');
    }
};
