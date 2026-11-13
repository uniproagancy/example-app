<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Support\Catalog;
use App\Support\CategoryTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_all_products_listing_shows_category_navigation_and_pagination(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $response = $this->get('/ka/catalog?all=1')
            ->assertOk()
            ->assertSee('<title>პროდუქცია — ELIO.GE</title>', false)
            ->assertSee('ყველა პროდუქტი')
            ->assertSee(__('catalog.load_more', [], 'ka'))
            ->assertSee(__('catalog.shown', ['shown' => 12, 'total' => 43], 'ka'));

        CategoryTree::roots()->each(fn (array $root) => $response->assertSee(url('/ka/categories/'.$root['slug']), false));

        $this->assertSame(12, substr_count($response->getContent(), 'wire:key="category-product-'));
    }

    public function test_catalog_opens_on_category_tiles_without_products(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $tiles = Catalog::categories()->filter(fn (array $category) => $category['count'] > 0)->sortByDesc('count')->values();

        $response = $this->get('/ka/catalog')
            ->assertOk()
            ->assertSee('wire:key="catalog-tile-'.$tiles->first()['key'].'"', false)
            ->assertSee($tiles->first()['url'], false)
            ->assertSee(__('shop.all_categories', [], 'ka'))
            ->assertSee(url('/ka/catalog?all=1'), false)
            ->assertDontSee('wire:key="category-product-', false)
            ->assertDontSee(__('catalog.load_more', [], 'ka'));

        $this->assertSame($tiles->count(), substr_count($response->getContent(), 'wire:key="catalog-tile-'));
    }

    public function test_typing_in_catalog_search_replaces_tiles_with_matching_products(): void
    {
        $iphone = Catalog::products()->firstWhere('sku', 'ip16');

        $component = Livewire::test('pages::catalog')
            ->assertSee('wire:key="catalog-tile-', false)
            ->set('search', 'iphone')
            ->assertDontSee('wire:key="catalog-tile-', false)
            ->assertSee('wire:key="category-product-'.$iphone['id'].'"', false);

        $this->assertNotEmpty($component->get('results'));
        $this->assertTrue($component->get('results')->every(fn (array $product) => str_contains(mb_strtolower($product['name'].' '.$product['subLabel']), 'iphone')));

        $component->set('search', 'zzqqxx')
            ->assertSee(__('catalog.search.empty_title', ['term' => 'zzqqxx']))
            ->call('clearSearch')
            ->assertSet('search', '')
            ->assertSee('wire:key="catalog-tile-', false);

        Livewire::withQueryParams(['q' => 'iPhone 16'])
            ->test('pages::catalog')
            ->assertSee('wire:key="category-product-'.$iphone['id'].'"', false)
            ->assertSee('„iPhone 16“', false);
    }

    public function test_search_understands_georgian_word_endings(): void
    {
        $products = collect([
            ['id' => 1, 'name' => 'Samsung ტელევიზორი 55"', 'brand' => 'Samsung', 'subLabel' => 'ტელევიზორები'],
            ['id' => 2, 'name' => 'LG მაცივარი', 'brand' => 'LG', 'subLabel' => 'მაცივრები'],
            ['id' => 3, 'name' => 'Sony ყურსასმენი', 'brand' => 'Sony', 'subLabel' => 'აუდიო'],
        ]);

        $this->assertSame([1], Catalog::search($products, 'ტელევიზორებს')->pluck('id')->all());
        $this->assertSame([3], Catalog::search($products, '  ყურსასმენები ')->pluck('id')->all());
        $this->assertSame([2], Catalog::search($products, 'LG')->pluck('id')->all());
        $this->assertSame([1, 2, 3], Catalog::search($products, '')->pluck('id')->all());
    }

    public function test_filters_narrow_results_and_reset_pagination(): void
    {
        $this->refreshApplicationWithLocale('en');

        $component = Livewire::test('pages::catalog')
            ->call('loadMore')
            ->assertSet('limit', 24)
            ->call('toggleBrand', 'Sony')
            ->assertSet('limit', 12);

        $this->assertSame(Catalog::products()->where('brand', 'Sony')->count(), $component->get('results')->count());
        $this->assertTrue($component->get('results')->every(fn (array $product) => $product['brand'] === 'Sony'));

        $component->set('maxPrice', 1000);
        $this->assertSame(2, $component->get('results')->count());
        $this->assertTrue($component->get('results')->every(fn (array $product) => $product['price'] <= 1000));

        $component->set('maxPrice', 1);
        $this->assertSame(0, $component->get('results')->count());
        $component->assertSee(__('catalog.empty_title', [], 'en'));

        $component->call('clearFilters');
        $this->assertSame(43, $component->get('results')->count());
    }

    public function test_flags_and_sorting(): void
    {
        $component = Livewire::test('pages::catalog')->call('toggleFlag', 'sale');

        $this->assertTrue($component->get('results')->every(fn (array $product) => $product['old'] !== null));
        $this->assertSame(Catalog::onSale()->count(), $component->get('results')->count());

        $component->call('toggleFlag', 'sale')->call('setSort', 'price_asc');
        $prices = $component->get('results')->pluck('price')->all();
        $sorted = $prices;
        sort($sorted);
        $this->assertSame($sorted, $prices);

        $component->call('setSort', 'price_desc');
        $this->assertSame(6999.0, $component->get('results')->first()['price']);

        $component->call('setSort', 'bogus');
        $this->assertSame('popular', $component->get('sort'));
    }

    public function test_popular_sort_puts_stock_then_best_sellers_first_and_price_sort_keeps_out_of_stock_last(): void
    {
        $cheapest = Product::query()->where('is_active', true)->orderBy('price')->firstOrFail();
        $cheapest->update(['stock' => 0]);
        $bestSeller = Product::query()->where('is_active', true)->whereKeyNot($cheapest->id)->orderByDesc('price')->firstOrFail();
        $order = Order::query()->create(['number' => 'EL-100001', 'status' => 'new', 'payment_method' => 'cash', 'first_name' => 'ა', 'phone' => '555', 'subtotal' => 1, 'discount' => 0, 'shipping' => 0, 'total' => 1, 'locale' => 'ka']);
        $order->items()->create(['product_id' => $bestSeller->id, 'name' => 'x', 'unit_price' => 1, 'quantity' => 3, 'total' => 3]);
        Catalog::flush();

        $popular = Livewire::test('pages::catalog')->get('results');
        $this->assertSame($bestSeller->id, $popular->first()['id']);
        $this->assertSame($cheapest->id, $popular->last()['id']);

        $byPrice = Livewire::test('pages::catalog')->call('setSort', 'price_asc')->get('results');
        $this->assertSame($cheapest->id, $byPrice->last()['id']);
        $inStock = $byPrice->where('inStock', true)->pluck('price')->all();
        $sorted = $inStock;
        sort($sorted);
        $this->assertSame($sorted, $inStock);
    }

    public function test_filters_are_read_from_the_query_string(): void
    {
        $this->refreshApplicationWithLocale('en');

        $ids = Catalog::products()->pluck('id', 'sku');

        $response = $this->get('/en/catalog?brand[]=Apple&min=3000&sort=price_asc')
            ->assertOk()
            ->assertSee(__('catalog.clear', [], 'en'))
            ->assertSeeInOrder([
                'wire:key="category-product-'.$ids['mba'].'"',
                'wire:key="category-product-'.$ids['ip16'].'"',
                'wire:key="category-product-'.$ids['mbp'].'"',
            ], false);

        $this->assertSame(3, substr_count($response->getContent(), 'wire:key="category-product-'));
    }

    public function test_category_listing_scopes_products_and_shows_sub_navigation(): void
    {
        $this->refreshApplicationWithLocale('en');

        $gaming = Category::query()->where('code', 'gaming')->first();

        $component = Livewire::test('pages::catalog', ['slug' => 'gaming'])
            ->assertSet('categoryId', $gaming->id)
            ->assertSee('Consoles')
            ->assertSee(url('/en/catalog'), false);

        $this->assertTrue($component->get('results')->every(fn (array $product) => $product['cat'] === 'gaming'));

        $this->get('/en/categories/consoles')
            ->assertOk()
            ->assertSee(url('/en/categories/gaming'), false)
            ->assertSee('Controllers');
    }

    public function test_menu_and_drawer_link_to_catalog_pages(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $this->get('/ka')
            ->assertSee('href="'.url('/ka/catalog').'"', false)
            ->assertDontSee('href="'.url('/ka/categories').'"', false);

        $this->get('/ka/categories')->assertNotFound();
    }

    public function test_header_search_offers_categories_from_every_level(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $categories = Catalog::searchableCategories();
        $child = $categories->first(fn (array $category) => $category['parent'] !== null);
        $root = CategoryTree::nodes()->firstWhere('id', CategoryTree::find($child['id'])['parent_id']);

        $this->assertGreaterThan(CategoryTree::roots()->count(), $categories->count());
        $this->assertSame($root['short'], $child['parent']);
        $this->assertSame(CategoryTree::url(CategoryTree::find($child['id'])), $child['url']);
        $this->assertSame(
            Catalog::products()->whereIn('categoryId', CategoryTree::find($root['id'])['subtree'])->count(),
            $categories->firstWhere('id', $root['id'])['count'],
        );

        $this->get('/ka')->assertOk()->assertSee('searchBox(', false);
    }

    public function test_home_section_tabs_name_subcategories_that_have_no_code(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $iphone = Product::query()->where('sku', 'ip16')->sole();
        $root = CategoryTree::find($iphone->category_id);
        $root = $root['parent_id'] ? CategoryTree::ancestors($root['id'])->first() : $root;
        Category::query()->whereKey($root['id'])->update(['show_on_main' => true]);

        $child = Category::createUnder($root['id'], 'ქეისები უკოდოდ');
        $iphone->update(['category_id' => $child->id]);
        CategoryTree::flush();
        Catalog::flush();

        $this->assertSame('ქეისები უკოდოდ', Catalog::label((string) $child->id));

        $this->get('/ka')
            ->assertOk()
            ->assertSee('ქეისები უკოდოდ</button>', false)
            ->assertDontSee('>'.$child->id.'</button>', false);
    }

    public function test_home_sections_show_only_the_busiest_subcategories_as_tabs(): void
    {
        $section = Catalog::sections()->sortByDesc(fn (array $section) => $section['products']->pluck('sub')->unique()->count())->first();
        $counts = $section['products']->countBy('sub')->sortDesc();

        $this->assertLessThanOrEqual(Catalog::SECTION_CHIPS, count($section['chips']));
        $this->assertSame($counts->keys()->take(Catalog::SECTION_CHIPS)->map(fn ($sub) => (string) $sub)->values()->all(), $section['chips']);
    }
}
