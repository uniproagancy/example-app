<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('supplier_sku', 64)->nullable()->after('sku');
        });

        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->json('label');
            $table->string('hex', 9)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('old_price', 10, 2)->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('position')->default(0);

            $table->index(['product_id', 'type']);
        });

        Schema::create('quick_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone', 32);
            $table->unsignedInteger('quantity')->default(1);
            $table->json('options')->nullable();
            $table->string('payment_method', 20);
            $table->string('bank', 20)->nullable();
            $table->unsignedTinyInteger('term_months')->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total', 10, 2);
            $table->string('locale', 5);
            $table->string('status', 20)->default('new');
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_orders');
        Schema::dropIfExists('product_options');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('supplier_sku');
        });
    }
};
