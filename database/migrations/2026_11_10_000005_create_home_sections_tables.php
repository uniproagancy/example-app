<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('key', 32)->unique();
            $table->json('title')->nullable();
            $table->json('kicker')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('perks', function (Blueprint $table) {
            $table->id();
            $table->string('icon', 32)->default('delivery');
            $table->json('title');
            $table->json('text')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('promos', function (Blueprint $table) {
            $table->id();
            $table->json('badge')->nullable();
            $table->json('title');
            $table->json('text')->nullable();
            $table->json('note')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('url')->nullable();
            $table->string('theme', 20)->default('navy');
            $table->string('icon', 32)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });

        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->json('text')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->boolean('show_on_home')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('show_on_home');
        });

        Schema::dropIfExists('deals');
        Schema::dropIfExists('promos');
        Schema::dropIfExists('perks');
        Schema::dropIfExists('home_blocks');
    }
};
