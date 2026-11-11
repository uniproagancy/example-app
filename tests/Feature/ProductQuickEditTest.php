<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Catalog;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductQuickEditTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function product(string $sku): Product
    {
        return Product::query()->where('sku', $sku)->sole();
    }

    public function test_newest_products_open_at_the_top_of_the_list(): void
    {
        $latestSeeded = Product::query()->latest('id')->first();
        $imported = $this->product('ip16')->replicate()->fill(['sku' => 'citrus-new-1', 'position' => 99999]);
        $imported->save();

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$imported, $latestSeeded], inOrder: true)
            ->sortTable('price', 'desc')
            ->sortTable()
            ->assertCanSeeTableRecords([$imported, $latestSeeded], inOrder: true);
    }

    public function test_selected_products_get_a_new_category_and_brand_together(): void
    {
        $laptops = Category::query()->where('code', 'laptops')->sole();
        $brand = Brand::query()->create(['name' => 'Elio Select']);
        $untouched = $this->product('s25');
        $before = $untouched->only(['category_id', 'brand_id', 'taxonomy_lock']);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('bulkEdit', [$this->product('ip16'), $this->product('ps5')], [
                'category_id' => $laptops->id,
                'brand_id' => $brand->id,
            ])
            ->assertHasNoTableBulkActionErrors()
            ->assertNotified();

        foreach (['ip16', 'ps5'] as $sku) {
            $product = $this->product($sku);
            $this->assertSame($laptops->id, $product->category_id);
            $this->assertSame($brand->id, $product->brand_id);
            $this->assertTrue($product->taxonomy_lock);
        }

        $this->assertSame($before, $untouched->fresh()->only(['category_id', 'brand_id', 'taxonomy_lock']));
        $this->assertSame('Elio Select', Catalog::findBySku('ip16')['brand']);
    }

    public function test_bulk_edit_leaves_empty_fields_alone_and_needs_at_least_one_choice(): void
    {
        $iphone = $this->product('ip16');
        $brand = Brand::query()->create(['name' => 'Elio Select']);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('bulkEdit', [$iphone], ['category_id' => null, 'brand_id' => null])
            ->assertHasTableBulkActionErrors(['category_id', 'brand_id']);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('bulkEdit', [$iphone], ['brand_id' => $brand->id, 'taxonomy_lock' => false])
            ->assertHasNoTableBulkActionErrors();

        $fresh = $iphone->fresh();
        $this->assertSame($iphone->category_id, $fresh->category_id);
        $this->assertSame($brand->id, $fresh->brand_id);
        $this->assertFalse($fresh->taxonomy_lock);
    }

    public function test_clicking_price_or_stock_opens_a_modal_that_saves_both(): void
    {
        $product = $this->product('ip16');

        Livewire::test(ListProducts::class)
            ->mountTableAction('editStock', $product)
            ->assertTableActionDataSet(['price' => $product->price, 'stock' => $product->stock])
            ->setTableActionData(['price' => 3500, 'old_price' => 3999, 'stock' => 9])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors()
            ->assertNotified();

        $product->refresh();
        $this->assertSame('3500.00', $product->price);
        $this->assertSame('3999.00', $product->old_price);
        $this->assertSame(9, $product->stock);
        $this->assertFalse($product->price_lock);
        $this->assertSame(3500, (int) Catalog::findBySku('ip16')['price']);

        Livewire::test(ListProducts::class)
            ->callTableAction('editPrice', $product, ['price' => 3400, 'old_price' => 3000, 'stock' => 9])
            ->assertHasTableActionErrors(['old_price']);

        Livewire::test(ListProducts::class)
            ->callTableAction('editPrice', $product, ['price' => 3400, 'old_price' => null, 'stock' => 9])
            ->assertHasNoTableActionErrors();

        $this->assertNull($product->fresh()->old_price);
    }

    public function test_new_price_on_an_imported_product_is_locked_against_the_next_import(): void
    {
        $product = $this->product('ip16');
        $supplier = Supplier::query()->where('code', 'midea')->sole();
        $product->offers()->create(['supplier_id' => $supplier->id, 'external_id' => 'X1', 'cost_price' => 3000, 'stock' => 2]);

        Livewire::test(ListProducts::class)
            ->mountTableAction('editPrice', $product)
            ->setTableActionData(['price' => 3333])
            ->assertTableActionDataSet(['price_lock' => true])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertTrue($product->fresh()->price_lock);
        $this->assertSame('3333.00', $product->fresh()->price);
    }
}
