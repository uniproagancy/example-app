<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('source', 20)->default('checkout')->after('status');
            $table->string('last_name', 60)->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('shipping_method', 20)->nullable()->change();
            $table->string('city', 60)->nullable()->change();
            $table->string('address')->nullable()->change();

            $table->index(['source', 'status']);
        });

        $statuses = ['processing' => 'confirmed', 'completed' => 'delivered'];

        DB::table('quick_orders')->orderBy('id')->each(function (object $quickOrder) use ($statuses): void {
            do {
                $number = 'EL-'.random_int(100000, 999999);
            } while (DB::table('orders')->where('number', $number)->exists());

            $orderId = DB::table('orders')->insertGetId([
                'number' => $number,
                'user_id' => $quickOrder->user_id,
                'status' => $statuses[$quickOrder->status] ?? $quickOrder->status,
                'source' => 'quick',
                'first_name' => $quickOrder->name,
                'phone' => $quickOrder->phone,
                'payment_method' => $quickOrder->payment_method,
                'bank' => $quickOrder->bank,
                'term_months' => $quickOrder->term_months,
                'subtotal' => $quickOrder->total,
                'discount' => 0,
                'shipping' => 0,
                'total' => $quickOrder->total,
                'locale' => $quickOrder->locale,
                'created_at' => $quickOrder->created_at,
                'updated_at' => $quickOrder->updated_at,
            ]);

            DB::table('order_items')->insert([
                'order_id' => $orderId,
                'product_id' => $quickOrder->product_id,
                'name' => DB::table('product_translations')
                    ->where('product_id', $quickOrder->product_id)
                    ->where('locale', $quickOrder->locale)
                    ->value('name') ?? (string) $quickOrder->product_id,
                'options' => $quickOrder->options,
                'unit_price' => $quickOrder->unit_price,
                'quantity' => $quickOrder->quantity,
                'total' => $quickOrder->total,
                'created_at' => $quickOrder->created_at,
                'updated_at' => $quickOrder->updated_at,
            ]);
        });

        Schema::dropIfExists('quick_orders');
    }

    public function down(): void
    {
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

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['source', 'status']);
            $table->dropColumn('source');
        });
    }
};
