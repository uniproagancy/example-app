<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->timestamp('supplier_ordered_at')->nullable()->after('serial_photo');
            $table->foreignId('supplier_ordered_by')->nullable()->after('supplier_ordered_at')->constrained('users')->nullOnDelete();
            $table->string('pickup_warehouse')->nullable()->after('supplier_ordered_by');
            $table->string('pickup_address')->nullable()->after('pickup_warehouse');
            $table->text('pickup_note')->nullable()->after('pickup_address');
            $table->timestamp('picked_up_at')->nullable()->after('pickup_note');
            $table->foreignId('picked_up_by')->nullable()->after('picked_up_at')->constrained('users')->nullOnDelete();

            $table->index('supplier_ordered_at');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['supplier_ordered_at']);
            $table->dropConstrainedForeignId('picked_up_by');
            $table->dropConstrainedForeignId('supplier_ordered_by');
            $table->dropColumn(['supplier_ordered_at', 'pickup_warehouse', 'pickup_address', 'pickup_note', 'picked_up_at']);
        });
    }
};
