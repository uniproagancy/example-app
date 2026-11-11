<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('driver');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->json('config')->nullable();
            $table->json('markup')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->change();
            $table->decimal('cost_price', 10, 2)->nullable()->after('old_price');
            $table->boolean('price_lock')->default(false)->after('cost_price');
            $table->boolean('taxonomy_lock')->default(false)->after('price_lock');
            $table->unsignedInteger('weight')->nullable()->after('stock');
            $table->string('variant_group', 64)->nullable()->after('weight');
            $table->boolean('is_preorder')->default(false)->after('is_new');
            $table->date('release_date')->nullable()->after('is_preorder');
            $table->timestamp('synced_at')->nullable()->after('position');

            $table->index('synced_at');
            $table->index('variant_group');
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->string('hash', 40)->nullable()->after('path');
            $table->index(['product_id', 'hash']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('created_by_supplier_id')->nullable()->after('parent_id')->constrained('suppliers')->nullOnDelete();
        });

        Schema::create('product_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 64);
            $table->decimal('cost_price', 10, 2);
            $table->decimal('old_cost_price', 10, 2)->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->string('external_category')->nullable();
            $table->string('external_brand')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'external_id']);
            $table->index(['product_id', 'cost_price']);
            $table->index(['supplier_id', 'external_category']);
        });

        Schema::create('supplier_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 64);
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->json('data')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'external_id']);
            $table->index(['supplier_id', 'quantity']);
        });

        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('type', 16)->default('select');
            $table->string('unit', 16)->nullable();
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_variant')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();
        });

        Schema::create('attribute_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('name');

            $table->unique(['attribute_id', 'locale']);
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('color_hex', 9)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['attribute_id', 'code']);
        });

        Schema::create('attribute_value_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('label');

            $table->unique(['attribute_value_id', 'locale']);
        });

        Schema::create('attribute_value_product', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();

            $table->primary(['product_id', 'attribute_value_id']);
            $table->index('attribute_value_id');
        });

        Schema::create('attribute_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['attribute_id', 'category_id']);
        });

        Schema::create('supplier_category_map', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('external_name');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamps();

            $table->unique(['supplier_id', 'external_name']);
        });

        Schema::create('supplier_attribute_map', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('external_name');
            $table->foreignId('attribute_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamps();

            $table->unique(['supplier_id', 'external_name']);
        });

        Schema::create('product_spec_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_spec_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('value');

            $table->unique(['product_spec_id', 'locale']);
        });

        Schema::table('product_specs', function (Blueprint $table) {
            $table->foreignId('attribute_id')->nullable()->after('product_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_key')->default(false)->after('attribute_id');
            $table->timestamps();
        });

        $this->moveSpecsOntoAttributes();

        Schema::table('product_specs', function (Blueprint $table) {
            $table->dropColumn(['name', 'value']);
            $table->unique(['product_id', 'attribute_id']);
        });
    }

    protected function moveSpecsOntoAttributes(): void
    {
        $attributes = [];
        $now = now();

        DB::table('product_specs')->orderBy('id')->each(function (object $spec) use (&$attributes, $now): void {
            $names = array_filter((array) json_decode((string) $spec->name, true));
            $values = array_filter((array) json_decode((string) $spec->value, true));
            $primary = trim((string) ($names['ka'] ?? reset($names) ?: ''));

            if ($primary === '') {
                DB::table('product_specs')->where('id', $spec->id)->delete();

                return;
            }

            $code = Str::limit(Str::slug(strtr(mb_strtolower($primary), self::GEORGIAN)) ?: 'spec-'.substr(md5($primary), 0, 8), 64, '');

            if (! isset($attributes[$code])) {
                $attributes[$code] = DB::table('attributes')->where('code', $code)->value('id')
                    ?? DB::table('attributes')->insertGetId([
                        'code' => $code, 'type' => 'select', 'is_filterable' => false, 'is_variant' => false,
                        'sort_order' => 100, 'created_at' => $now, 'updated_at' => $now,
                    ]);

                foreach ($names as $locale => $name) {
                    DB::table('attribute_translations')->updateOrInsert(
                        ['attribute_id' => $attributes[$code], 'locale' => $locale],
                        ['name' => trim((string) $name)],
                    );
                }
            }

            if (DB::table('product_specs')->where('product_id', $spec->product_id)->where('attribute_id', $attributes[$code])->exists()) {
                DB::table('product_specs')->where('id', $spec->id)->delete();

                return;
            }

            DB::table('product_specs')->where('id', $spec->id)->update([
                'attribute_id' => $attributes[$code],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($values as $locale => $value) {
                if (trim((string) $value) !== '') {
                    DB::table('product_spec_translations')->insert([
                        'product_spec_id' => $spec->id,
                        'locale' => $locale,
                        'value' => Str::limit(trim((string) $value), 255, ''),
                    ]);
                }
            }
        });
    }

    protected const GEORGIAN = [
        'ა' => 'a', 'ბ' => 'b', 'გ' => 'g', 'დ' => 'd', 'ე' => 'e', 'ვ' => 'v', 'ზ' => 'z', 'თ' => 't', 'ი' => 'i',
        'კ' => 'k', 'ლ' => 'l', 'მ' => 'm', 'ნ' => 'n', 'ო' => 'o', 'პ' => 'p', 'ჟ' => 'zh', 'რ' => 'r', 'ს' => 's',
        'ტ' => 't', 'უ' => 'u', 'ფ' => 'f', 'ქ' => 'k', 'ღ' => 'gh', 'ყ' => 'q', 'შ' => 'sh', 'ჩ' => 'ch', 'ც' => 'ts',
        'ძ' => 'dz', 'წ' => 'ts', 'ჭ' => 'ch', 'ხ' => 'kh', 'ჯ' => 'j', 'ჰ' => 'h',
    ];

    public function down(): void
    {
        Schema::table('product_specs', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'attribute_id']);
            $table->json('name')->nullable();
            $table->json('value')->nullable();
        });

        Schema::table('product_specs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attribute_id');
            $table->dropColumn(['is_key', 'created_at', 'updated_at']);
        });

        Schema::dropIfExists('product_spec_translations');
        Schema::dropIfExists('supplier_attribute_map');
        Schema::dropIfExists('supplier_category_map');
        Schema::dropIfExists('attribute_category');
        Schema::dropIfExists('attribute_value_product');
        Schema::dropIfExists('attribute_value_translations');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attribute_translations');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('supplier_stocks');
        Schema::dropIfExists('product_offers');

        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_supplier_id');
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'hash']);
            $table->dropColumn('hash');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['synced_at']);
            $table->dropIndex(['variant_group']);
            $table->dropColumn(['cost_price', 'price_lock', 'taxonomy_lock', 'weight', 'variant_group', 'is_preorder', 'release_date', 'synced_at']);
        });

        Schema::dropIfExists('suppliers');
    }
};
