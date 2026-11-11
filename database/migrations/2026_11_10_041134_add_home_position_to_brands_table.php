<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->unsignedInteger('home_position')->default(0)->after('show_on_home');
            $table->boolean('show_on_home')->default(false)->change();
        });

        DB::table('brands')->update(['show_on_home' => false]);
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('home_position');
            $table->boolean('show_on_home')->default(true)->change();
        });
    }
};
