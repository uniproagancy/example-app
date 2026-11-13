<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Support\Catalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_products_are_seeded_into_the_database(): void
    {
        $this->assertSame(43, Product::query()->count());

        $iphone = Product::query()->where('sku', 'ip16')->with(['translations', 'brand', 'category.translations', 'specs', 'options'])->first();

        $this->assertSame('Apple', $iphone->brand->name);
        $this->assertSame('apple-iphone', $iphone->category->code);
        $this->assertSame('apple-iphone-16-pro', $iphone->translation('ka')->slug);
        $this->assertCount(16, $iphone->specs);
        $this->assertCount(8, $iphone->options);
        $this->assertSame('MYNF3/A', $iphone->supplier_sku);
    }

    public function test_product_page_renders_details_specs_and_similar_products(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $this->get('/ka/product/apple-iphone-16-pro')
            ->assertOk()
            ->assertSee('<title>Apple iPhone 16 Pro — ფასი, მახასიათებლები | ELIO.GE</title>', false)
            ->assertSee('Desert')
            ->assertSee('512 GB')
            ->assertSee(__('product.id', ['id' => Product::query()->where('sku', 'ip16')->value('id')], 'ka'))
            ->assertDontSee('MYNF3/A')
            ->assertDontSee('SKU: IP16')
            ->assertSee(__('product.quick.title', [], 'ka'))
            ->assertSee('3 899 ₾')
            ->assertSee('4 199 ₾')
            ->assertSee('მარაგშია')
            ->assertSee('A18 Pro')
            ->assertSee('ტიტანის კორპუსით')
            ->assertSee(url('/ka/categories/apple-iphone'), false)
            ->assertSee(url('/ka/categories/mobile-phones-and-accessories'), false)
            ->assertSee('iPhone 16e, 128 GB, White')
            ->assertSee('hreflang="en" href="'.url('/en/product/apple-iphone-16-pro').'"', false);
    }

    public function test_product_page_is_translated_and_redirects_foreign_slugs(): void
    {
        $this->refreshApplicationWithLocale('en');

        $this->get('/en/product/dell-ultrasharp-27-4k-usbc-monitor')
            ->assertOk()
            ->assertSee('UltraSharp 27″ 4K USB‑C Monitor')
            ->assertSee('Specifications');

        $this->get('/en/product/dell-ultrasharp-27-4k-usbc-monitor')
            ->assertSee('hreflang="ka" href="'.url('/ka/product/dell-ultrasharp-27-4k-usbc-monitor').'"', false);

        $this->get('/ka/produkti/dell-ultrasharp-27-4k-usbc-monitor')->assertNotFound();

        $this->get('/en/product/does-not-exist')->assertNotFound();
    }

    public function test_inactive_products_are_hidden_everywhere(): void
    {
        $this->refreshApplicationWithLocale('en');

        Product::query()->where('sku', 'xsx')->first()->update(['is_active' => false]);

        $this->assertNull(Catalog::findBySku('xsx'));

        $this->get('/en/product/microsoft-xbox-series-x-1-tb')->assertNotFound();
        $this->get('/en')->assertOk()->assertDontSee('Xbox Series X, 1 TB');
    }

    public function test_price_changes_in_database_show_on_the_site(): void
    {
        $this->refreshApplicationWithLocale('en');

        Product::query()->where('sku', 'ds')->first()->update(['price' => 555, 'old_price' => 700, 'stock' => 0]);

        $this->get('/en/product/sony-dualsense-edge-wireless-controller')
            ->assertOk()
            ->assertSee('555 ₾')
            ->assertSee('700 ₾')
            ->assertSee('Out of stock')
            ->assertSee('Temporarily unavailable');
    }

    public function test_product_cards_link_to_product_pages(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $this->get('/ka')
            ->assertOk()
            ->assertSee('href="'.url('/ka/product/apple-iphone-16-pro').'"', false);
    }

    public function test_specs_and_description_are_tabs_shown_one_at_a_time(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $html = $this->get('/ka/product/apple-iphone-16-pro')->assertOk()->getContent();

        $this->assertStringContainsString('role="tablist"', $html);
        $this->assertMatchesRegularExpression('/<section id="specs"[^>]*x-show="tab === \'specs\'"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<section id="specs"[^>]*x-cloak/', $html);
        $this->assertMatchesRegularExpression('/<section id="desc"[^>]*x-cloak/', $html);

        $iphone = Product::query()->where('sku', 'ip16')->sole();
        $iphone->specs()->delete();
        $iphone->options()->delete();
        Catalog::flush();

        $html = $this->get('/ka/product/apple-iphone-16-pro')->assertOk()->getContent();

        $this->assertStringNotContainsString('role="tablist"', $html);
        $this->assertStringNotContainsString('<section id="specs"', $html);
        $this->assertMatchesRegularExpression('/<section id="desc"[^>]*x-show="tab === \'desc\'"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<section id="desc"[^>]*x-cloak/', $html);
    }

    public function test_admins_also_see_the_sku_with_a_link_to_edit(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $iphone = Product::query()->where('sku', 'ip16')->sole();
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/ka/product/apple-iphone-16-pro')
            ->assertOk()
            ->assertSee('SKU: IP16')
            ->assertSee('MYNF3/A')
            ->assertSee(url('/admin/products/'.$iphone->id.'/edit'), false);

        $this->actingAs(User::factory()->create());

        $this->get('/ka/product/apple-iphone-16-pro')
            ->assertOk()
            ->assertDontSee('SKU: IP16');
    }
}
