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

        CategoryTree::roots()->each(fn (array $root) => Catalog::productsIn($root)->isNotEmpty()
            ? $response->assertSee('wire:key="nav-'.$root['id'].'"', false)
            : $response->assertDontSee('wire:key="nav-'.$root['id'].'"', false));

        $this->assertSame(12, substr_count($response->getContent(), 'wire:key="category-product-'));
    }

    public function test_products_added_in_the_last_two_days_get_the_new_badge(): void
    {
        $speaker = Product::query()->where('sku', 'jbl')->sole();
        $speaker->forceFill(['is_new' => false, 'old_price' => null, 'created_at' => now()->subDay()])->save();
        Catalog::flush();

        $shown = Catalog::find($speaker->id);
        $this->assertTrue($shown['isNew']);
        $this->assertSame(__('shop.badge_new'), $shown['badge']);

        $speaker->forceFill(['created_at' => now()->subDays(3)])->save();
        Catalog::flush();

        $shown = Catalog::find($speaker->id);
        $this->assertFalse($shown['isNew']);
        $this->assertNull($shown['badge']);

        $this->travel(3)->days();
        $speaker->forceFill(['is_new' => true])->save();
        Catalog::flush();

        $this->assertTrue(Catalog::find($speaker->id)['isNew']);
    }

    public function test_out_of_stock_products_are_hidden_everywhere_on_the_site(): void
    {
        $speaker = Product::query()->where('sku', 'jbl')->sole();
        $this->assertNotNull(Catalog::find($speaker->id));
        $countBefore = Catalog::categoryCounts()->get($speaker->category_id);

        $speaker->update(['stock' => 0]);
        Catalog::flush();

        $this->assertNull(Catalog::find($speaker->id));
        $this->assertFalse(Catalog::products()->contains('id', $speaker->id));
        $this->assertSame($countBefore - 1, Catalog::categoryCounts()->get($speaker->category_id, 0));
        $this->assertSame([], Catalog::searchCandidates('jbl'));
        $this->get(route('search.suggest', ['q' => 'jbl']))->assertOk()->assertJsonPath('total', 0);
    }

    public function test_header_search_asks_the_server_instead_of_shipping_every_product(): void
    {
        $this->refreshApplicationWithLocale('ka');
        $speaker = Catalog::find(Product::query()->where('sku', 'jbl')->value('id'));
        Catalog::flush();

        $page = $this->get('/ka')->assertOk()->getContent();
        $this->assertStringNotContainsString(e(json_encode($speaker['priceLabel'])), $page);

        $this->get(route('search.suggest', ['q' => mb_substr($speaker['name'], 0, 6)]))
            ->assertOk()
            ->assertJsonPath('products.0.id', $speaker['id'])
            ->assertJsonStructure(['products' => [['id', 'url', 'image', 'brand', 'name', 'subLabel', 'priceLabel']], 'total']);

        $this->get(route('search.suggest', ['q' => '']))->assertOk()->assertExactJson(['products' => [], 'total' => 0]);
    }

    public function test_home_sections_list_newest_products_first_unless_a_position_is_set(): void
    {
        Product::query()->update(['position' => 0]);
        Catalog::flush();

        $section = Catalog::sections()->first(fn (array $section) => $section['products']->count() >= 3);
        $order = CategoryTree::order();
        $products = $section['products'];

        $this->assertSame($products->pluck('categoryId')->sortBy(fn (int $id) => $order[$id] ?? PHP_INT_MAX)->values()->all(), $products->pluck('categoryId')->all());

        foreach ($products->groupBy('categoryId') as $sameCategory) {
            $this->assertSame($sameCategory->pluck('id')->sortDesc()->values()->all(), $sameCategory->pluck('id')->all());
        }

        $oldest = $products->pluck('id')->min();

        Product::query()->whereKey($oldest)->update(['position' => -1]);
        Catalog::flush();

        $this->assertSame($oldest, Catalog::sections()->firstWhere('id', $section['id'])['products']->first()['id']);
    }

    public function test_home_sections_render_a_limited_number_of_products_per_category(): void
    {
        $section = Catalog::sections()->first();
        $source = Product::query()->findOrFail($section['products']->first()['id']);

        foreach (range(1, 40) as $number) {
            $copy = $source->replicate()->fill(['sku' => 'bulk-'.$number]);
            $copy->save();
            $copy->saveTranslations([
                'ka' => ['name' => 'დამატებითი '.$number, 'slug' => 'bulk-ka-'.$number],
                'en' => ['name' => 'Extra '.$number, 'slug' => 'bulk-en-'.$number],
            ]);
        }

        Catalog::flush();

        $all = Catalog::productsIn(CategoryTree::roots()->firstWhere('id', $section['key']));
        $section = Catalog::sections()->firstWhere('id', $section['id']);

        $this->assertGreaterThan(Catalog::SECTION_PRODUCTS + Catalog::SECTION_CHIP_PRODUCTS * Catalog::SECTION_CHIPS, $all->count());
        $this->assertLessThanOrEqual(Catalog::SECTION_PRODUCTS + Catalog::SECTION_CHIP_PRODUCTS * Catalog::SECTION_CHIPS, $section['products']->count());
        $this->assertSame($all->take(Catalog::SECTION_PRODUCTS)->pluck('id')->all(), $section['products']->take(Catalog::SECTION_PRODUCTS)->pluck('id')->all());

        foreach ($section['chips'] as $chip) {
            $this->assertTrue($section['products']->contains(fn (array $product) => (string) $product['sub'] === $chip));
        }
    }

    public function test_catalog_opens_on_category_tiles_without_products(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $tiles = Catalog::categories()->filter(fn (array $category) => $category['count'] > 0)->values();

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

    public function test_category_tiles_and_search_categories_follow_the_admin_order(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $roots = Catalog::categories()->values();
        $this->assertGreaterThan(1, $roots->count());

        $roots->reverse()->values()->each(fn (array $root, int $position) => Category::query()->whereKey($root['key'])->update(['position' => $position]));
        CategoryTree::flush();
        Catalog::flush();

        $html = $this->get('/ka/catalog')->assertOk()->getContent();
        $positions = $roots->reverse()->values()->map(fn (array $root) => strpos($html, 'wire:key="catalog-tile-'.$root['key'].'"'));

        $this->assertNotContains(false, $positions->all());
        $this->assertSame($positions->sort()->values()->all(), $positions->all());

        $this->assertSame(
            $roots->reverse()->pluck('url')->values()->all(),
            Catalog::searchableCategories()->where('depth', 0)->pluck('url')->values()->all(),
        );
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

    public function test_brand_pages_list_the_brands_stocked_products_and_reach_the_sitemap(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $speaker = Product::query()->with('brand')->where('sku', 'jbl')->sole();
        $brand = $speaker->brand;
        $others = Product::query()->where('is_active', true)->where('stock', '>', 0)->where('brand_id', '!=', $brand->id)->with('kaTranslation')->first();

        $page = $this->get('/ka/brands/'.$brand->slug)
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('brand', ['brand' => $brand->slug]).'">', false)
            ->assertSee(__('catalog.brand_title', ['name' => $brand->name]).' — ', false)
            ->assertSee('wire:key="category-product-'.$speaker->id.'"', false)
            ->assertDontSee('wire:key="category-product-'.$others->id.'"', false);

        $this->assertStringContainsString('"BreadcrumbList"', $page->getContent());

        $this->get('/ka/brands/no-such-brand')->assertNotFound();

        $this->get('/sitemap.xml')->assertOk()->assertSee('/ka/brands/'.$brand->slug, false);

        Product::query()->where('brand_id', $brand->id)->update(['stock' => 0]);
        Catalog::flush();

        $this->get('/ka/brands/'.$brand->slug)->assertNotFound();
    }

    public function test_search_matches_across_alphabets_and_synonyms(): void
    {
        $products = collect([
            ['id' => 1, 'name' => 'Samsung ტელევიზორი 55"', 'brand' => 'Samsung', 'subLabel' => 'ტელევიზორები'],
            ['id' => 2, 'name' => 'Apple iPhone 16 Pro', 'brand' => 'Apple', 'subLabel' => 'სმარტფონები'],
            ['id' => 3, 'name' => 'Lenovo IdeaPad ლეპტოპი', 'brand' => 'Lenovo', 'subLabel' => 'ლეპტოპები'],
            ['id' => 4, 'name' => 'Xiaomi ხელსაწყოების ნაკრები', 'brand' => 'Xiaomi', 'subLabel' => 'ხელსაწყოები'],
        ]);

        $found = fn (string $query) => Catalog::search($products, $query)->pluck('id')->all();

        $this->assertSame([1], $found('სამსუნგი'));
        $this->assertSame([1], $found('televizori'));
        $this->assertSame([1], $found('ტვ'));
        $this->assertSame([2], $found('აიფონი'));
        $this->assertSame([2], $found('aifoni'));
        $this->assertSame([3], $found('notebook'));
        $this->assertSame([3], $found('ლაპტოპი'));
        $this->assertSame([4], $found('xelsats'));
        $this->assertSame([4], $found('შიაომი'));
        $this->assertSame([], $found('მაცივარი'));
    }

    public function test_header_search_finds_products_typed_in_the_other_alphabet(): void
    {
        $speaker = Catalog::find(Product::query()->where('sku', 'jbl')->value('id'));
        $georgian = strtr(mb_strtolower($speaker['brand']), ['j' => 'ჯ', 'b' => 'ბ', 'l' => 'ლ']);

        $this->assertContains($speaker['id'], Catalog::searchCandidates($georgian));
        $this->get(route('search.suggest', ['q' => $georgian]))->assertOk()->assertJsonPath('products.0.id', $speaker['id']);
    }

    public function test_filters_narrow_results_and_reset_pagination(): void
    {
        $this->refreshApplicationWithLocale('ka');

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
        $component->assertSee(__('catalog.empty_title', [], 'ka'));

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

    public function test_popular_sort_puts_best_sellers_first_and_out_of_stock_products_are_not_listed(): void
    {
        $cheapest = Product::query()->where('is_active', true)->orderBy('price')->firstOrFail();
        $cheapest->update(['stock' => 0]);
        $bestSeller = Product::query()->where('is_active', true)->whereKeyNot($cheapest->id)->orderByDesc('price')->firstOrFail();
        $order = Order::query()->create(['number' => 'EL-100001', 'status' => 'new', 'payment_method' => 'cash', 'first_name' => 'ა', 'phone' => '555', 'subtotal' => 1, 'discount' => 0, 'shipping' => 0, 'total' => 1, 'locale' => 'ka']);
        $order->items()->create(['product_id' => $bestSeller->id, 'name' => 'x', 'unit_price' => 1, 'quantity' => 3, 'total' => 3]);
        Catalog::flush();

        $popular = Livewire::test('pages::catalog')->get('results');
        $this->assertSame($bestSeller->id, $popular->first()['id']);
        $this->assertFalse($popular->contains('id', $cheapest->id));

        $byPrice = Livewire::test('pages::catalog')->call('setSort', 'price_asc')->get('results');
        $this->assertFalse($byPrice->contains('id', $cheapest->id));
        $inStock = $byPrice->where('inStock', true)->pluck('price')->all();
        $sorted = $inStock;
        sort($sorted);
        $this->assertSame($sorted, $inStock);
    }

    public function test_filters_are_read_from_the_query_string(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $ids = Catalog::products()->pluck('id', 'sku');

        $response = $this->get('/ka/catalog?brand[]=Apple&min=3000&sort=price_asc')
            ->assertOk()
            ->assertSee(__('catalog.clear', [], 'ka'))
            ->assertSeeInOrder([
                'wire:key="category-product-'.$ids['mba'].'"',
                'wire:key="category-product-'.$ids['ip16'].'"',
                'wire:key="category-product-'.$ids['mbp'].'"',
            ], false);

        $this->assertSame(3, substr_count($response->getContent(), 'wire:key="category-product-'));
    }

    public function test_listings_follow_the_category_order_set_in_admin(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $consoles = Category::query()->where('code', 'consoles')->sole();
        $controllers = Category::query()->where('code', 'controllers')->sole();
        $subOf = fn ($results) => $results->map(fn (array $product) => $product['categoryId'] === $controllers->id ? 'controllers' : 'consoles')->unique()->values()->all();

        Product::query()->update(['position' => 0]);
        $controllers->update(['position' => 0]);
        $consoles->update(['position' => 1]);
        Category::query()->where('code', 'gaming')->update(['show_on_main' => true]);
        CategoryTree::flush();
        Catalog::flush();

        $this->assertSame(['controllers', 'consoles'], $subOf(Livewire::test('pages::catalog', ['slug' => 'gaming'])->get('results')));
        $this->assertSame($controllers->id, Catalog::sections()->firstWhere('id', 'gaming')['products']->first()['categoryId']);

        $controllers->update(['position' => 2]);
        CategoryTree::flush();
        Catalog::flush();

        $this->assertSame(['consoles', 'controllers'], $subOf(Livewire::test('pages::catalog', ['slug' => 'gaming'])->get('results')));
    }

    public function test_category_listing_scopes_products_and_shows_sub_navigation(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $gaming = Category::query()->where('code', 'gaming')->first();

        $component = Livewire::test('pages::catalog', ['slug' => 'gaming'])
            ->assertSet('categoryId', $gaming->id)
            ->assertSee('კონსოლები')
            ->assertSee(url('/ka/catalog'), false);

        $this->assertTrue($component->get('results')->every(fn (array $product) => $product['cat'] === 'gaming'));

        $this->get('/ka/categories/consoles')
            ->assertOk()
            ->assertSee(url('/ka/categories/gaming'), false)
            ->assertSee('ჯოისტიკები');
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

    public function test_catalog_menu_hides_categories_without_products(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $root = CategoryTree::roots()->first(fn (array $node) => Catalog::productsIn($node)->isNotEmpty());
        $emptyGroup = Category::createUnder($root['id'], 'ცარიელი ჯგუფი');
        Category::createUnder($emptyGroup->id, 'ცარიელი ქვეჯგუფი');
        Category::createUnder(null, 'ცარიელი კატეგორია');

        $categories = Catalog::categories();
        $names = $categories->flatMap(fn (array $category) => [
            $category['name'],
            ...collect($category['groups'])->flatMap(fn (array $group) => [$group['name'], ...collect($group['subs'])->pluck('name')]),
        ]);

        $this->assertTrue($categories->contains('key', $root['id']));
        $this->assertNotContains('ცარიელი ჯგუფი', $names);
        $this->assertNotContains('ცარიელი ქვეჯგუფი', $names);
        $this->assertNotContains('ცარიელი კატეგორია', $names);
        $this->assertTrue($categories->every(fn (array $category) => $category['count'] > 0 && collect($category['groups'])->every(fn (array $group) => $group['count'] > 0)));

        $this->get('/ka')->assertOk()->assertDontSee('ცარიელი კატეგორია');
    }
}
