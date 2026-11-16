<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['is_active', 'deleted_at', 'position'], 'products_listing_index');
            $table->index(['is_active', 'stock'], 'products_stock_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->index('handled_at');
            $table->index('created_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index('issued_on');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_listing_index');
            $table->dropIndex('products_stock_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropIndex(['handled_at']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['issued_on']);
        });
    }
};
