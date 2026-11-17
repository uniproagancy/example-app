<?php

namespace Tests\Feature;

use App\Console\Commands\StandardizeFilters;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductSpec;
use App\Support\Catalog;
use App\Support\StandardAxes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StandardizeFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    protected function spec(Product $product, Attribute $attribute, string $value): void
    {
        $spec = ProductSpec::query()->create(['product_id' => $product->id, 'attribute_id' => $attribute->id, 'position' => 0]);
        $spec->saveTranslations(['ka' => ['value' => $value], 'en' => ['value' => $value]]);
    }

    /**
     * @return array{0: Product, 1: Product, 2: Attribute}
     */
    protected function phones(): array
    {
        $old = Attribute::query()->create(['code' => 'shida-mekhsiereba', 'type' => 'select', 'is_filterable' => true]);
        $old->saveTranslations(['ka' => ['name' => 'შიდა მეხსიერება'], 'en' => ['name' => 'Internal memory']]);

        [$small, $large] = Product::query()->whereIn('sku', ['ip16', 'px'])->orderBy('sku')->get()->all();
        $this->spec($small, $old, '256GB');
        $this->spec($large, $old, '512 GB');
        Product::query()->whereKey([$small->id, $large->id])->update(['variant_group' => 'g-standard']);

        return [$small, $large, $old];
    }

    /**
     * @return array<string, string>
     */
    protected function snapshot(): array
    {
        return collect(['attributes', 'attribute_values', 'attribute_value_product', 'attribute_translations', 'attribute_value_translations'])
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->get()->toJson()])
            ->all();
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $this->phones();
        $before = $this->snapshot();

        $this->artisan('catalog:standardize')
            ->expectsOutputToContain('from specifications: 2')
            ->expectsOutputToContain('Nothing was changed')
            ->assertSuccessful();

        $this->assertSame($before, $this->snapshot());
    }

    public function test_applying_files_one_standard_value_and_the_product_page_offers_it(): void
    {
        $this->refreshApplicationWithLocale('ka');
        Storage::fake('local');
        [$small, $large, $old] = $this->phones();

        $this->artisan('catalog:standardize', ['--apply' => true])->assertSuccessful();

        $storage = Attribute::query()->where('code', StandardAxes::STORAGE)->sole();
        $this->assertTrue($storage->is_filterable);
        $this->assertTrue($storage->is_variant);
        $this->assertFalse($old->fresh()->is_filterable);
        $this->assertNotNull($old->fresh(), 'The old filter is hidden, never deleted.');
        $this->assertSame(['256 GB'], $small->attributeValues()->where('attribute_id', $storage->id)->get()->map(fn ($value) => $value->label)->all());

        Catalog::flush();
        $this->get(Catalog::find($small->id)['url'])
            ->assertOk()
            ->assertSee('მეხსიერება')
            ->assertSee('512 GB')
            ->assertSee('href="'.Catalog::find($large->id)['url'].'"', false);
    }

    public function test_undo_puts_everything_back_exactly(): void
    {
        $this->phones();
        $before = $this->snapshot();

        $this->artisan('catalog:standardize', ['--apply' => true])->assertSuccessful();
        $log = collect(Storage::disk('local')->files(StandardizeFilters::LOG_DIRECTORY))->sole();

        $this->artisan('catalog:standardize', ['--undo' => $log])->assertSuccessful();

        $this->assertSame($before, $this->snapshot());
        Storage::disk('local')->assertExists($log.'.undone');
    }

    public function test_products_that_already_have_a_standard_value_keep_it(): void
    {
        [$small] = $this->phones();

        $this->artisan('catalog:standardize', ['--apply' => true])->assertSuccessful();
        $storage = Attribute::query()->where('code', StandardAxes::STORAGE)->sole();
        $chosen = $storage->values()->create(['code' => '1-tb']);
        $chosen->saveTranslations(['ka' => ['label' => '1 TB'], 'en' => ['label' => '1 TB']]);
        $small->attributeValues()->detach($storage->values()->pluck('id'));
        $small->attributeValues()->attach($chosen->id);

        $this->artisan('catalog:standardize', ['--apply' => true])
            ->expectsOutputToContain('already filed: 2')
            ->assertSuccessful();

        $this->assertSame(['1-tb'], $small->attributeValues()->where('attribute_id', $storage->id)->pluck('code')->all());
    }
}
