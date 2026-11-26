<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_courier')->default(false)->after('is_operator');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('serial_photo')->nullable()->after('serial_number');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('serial_photo');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_courier');
        });
    }
};
