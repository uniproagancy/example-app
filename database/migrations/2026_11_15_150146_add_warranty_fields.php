<?php

use App\Support\Warranty;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedSmallInteger('warranty_months')->nullable()->after('google_category_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('warranty_months')->nullable()->after('weight');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('warranty_months')->nullable()->after('total');
            $table->string('serial_number', 100)->nullable()->after('warranty_months');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('paid_at');
            $table->date('warranty_starts_at')->nullable()->after('completed_at');
            $table->timestamp('warranty_issued_at')->nullable()->after('warranty_starts_at');
        });

        DB::table('orders')->where('status', 'completed')->orderBy('id')->each(function (object $order): void {
            $completedAt = DB::table('order_events')
                ->where('order_id', $order->id)
                ->where('type', 'status')
                ->where('to', 'completed')
                ->max('created_at');

            DB::table('orders')->where('id', $order->id)->update(['completed_at' => $completedAt ?? $order->updated_at]);
        });

        DB::table('order_items')->whereNull('warranty_months')->orderBy('id')->each(function (object $item): void {
            DB::table('order_items')->where('id', $item->id)->update(['warranty_months' => Warranty::monthsFor($item->product_id)]);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['completed_at', 'warranty_starts_at', 'warranty_issued_at']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['warranty_months', 'serial_number']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('warranty_months');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('warranty_months');
        });
    }
};
