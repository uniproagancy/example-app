<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Feeds\FacebookFeed;
use App\Services\Feeds\GoogleFeed;
use App\Support\Catalog;
use App\Support\CategoryTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use SimpleXMLElement;
use Tests\TestCase;

class ProductFeedTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    protected function television(): Product
    {
        $category = Category::createUnder(null, 'ტელევიზორი', 'Televisions', ['google_category_id' => 404]);
        $brand = Brand::query()->firstOrCreate(['name' => 'Samsung'], ['slug' => 'samsung']);
        $product = Product::query()->where('sku', 'ip16')->sole()->replicate();
        $product->fill(['sku' => '8806095123456', 'category_id' => $category->id, 'brand_id' => $brand->id, 'price' => 1899, 'old_price' => 2199, 'stock' => 4, 'weight' => 14500, 'variant_group' => null]);
        $product->save();
        $product->saveTranslations([
            'ka' => ['name' => 'Samsung Crystal UHD 55 ტელევიზორი', 'slug' => 'samsung-crystal-uhd-55', 'description' => '<p>4K ტელევიზორი</p>'],
            'en' => ['name' => 'Samsung Crystal UHD 55 TV', 'slug' => 'samsung-crystal-uhd-55-en'],
        ]);
        $product->images()->create(['path' => 'products/tv/front.jpg', 'position' => 0]);
        $product->images()->create(['path' => 'products/tv/side.jpg', 'position' => 1]);

        $attribute = Attribute::query()->create(['code' => 'ekranis-zoma', 'type' => 'select', 'is_filterable' => true]);
        $attribute->saveTranslations(['ka' => ['name' => 'ეკრანის ზომა'], 'en' => ['name' => 'Screen size']]);
        $value = $attribute->values()->create(['code' => '55']);
        $value->saveTranslations(['ka' => ['label' => '55"'], 'en' => ['label' => '55"']]);
        $product->attributeValues()->sync([$value->id]);

        CategoryTree::flush();
        Catalog::flush();

        return $product;
    }

    protected function item(string $xml, int $id): ?SimpleXMLElement
    {
        $feed = new SimpleXMLElement($xml);
        $feed->registerXPathNamespace('g', 'http://base.google.com/ns/1.0');

        return $feed->xpath("//item[g:id='{$id}']")[0] ?? null;
    }

    public function test_facebook_feed_lists_products_with_sale_price_labels_and_main_feature(): void
    {
        $tv = $this->television();

        $path = app(FacebookFeed::class)->generate();
        $xml = file_get_contents($path);
        $item = $this->item($xml, $tv->id);
        $g = $item->children('http://base.google.com/ns/1.0');

        $this->assertSame('Samsung Crystal UHD 55 ტელევიზორი', (string) $g->title);
        $this->assertSame('2199.00 GEL', (string) $g->price);
        $this->assertSame('1899.00 GEL', (string) $g->sale_price);
        $this->assertSame('in stock', (string) $g->availability);
        $this->assertSame('404', (string) $g->google_product_category);
        $this->assertSame('product_default_group'.$tv->id, (string) $g->item_group_id);
        $this->assertStringContainsString('/ka/product/samsung-crystal-uhd-55?utm_source=facebook', (string) $g->link);
        $this->assertStringEndsWith('products/tv/front.jpg', (string) $g->image_link);
        $this->assertStringEndsWith('products/tv/side.jpg', (string) $g->additional_image_link);
        $this->assertSame('14.50 kg', (string) $g->shipping_weight);
        $this->assertSame('თვეში 80 ₾-დან', (string) $g->custom_label_0);
        $this->assertSame('140 სმ', (string) $g->short_description);
        $this->assertSame('140 სმ', (string) $g->custom_label_1);
        $this->assertSame('2 199 ₾', (string) $g->custom_label_4);
    }

    public function test_cheap_and_imageless_products_stay_out_and_hidden_categories_are_excluded(): void
    {
        $tv = $this->television();
        $path = app(FacebookFeed::class)->generate();
        $xml = file_get_contents($path);

        $withoutImages = Product::query()->where('is_active', true)->whereDoesntHave('images')->value('id');
        $this->assertNotNull($this->item($xml, $tv->id));
        $this->assertNull($this->item($xml, $withoutImages));

        $tv->update(['price' => 50, 'old_price' => null]);
        Catalog::flush();
        $this->assertNull($this->item(file_get_contents(app(FacebookFeed::class)->generate()), $tv->id));

        $tv->update(['price' => 1899]);
        Category::query()->whereKey($tv->category_id)->update(['in_feed' => false]);
        Catalog::flush();
        $this->assertNull($this->item(file_get_contents(app(FacebookFeed::class)->generate()), $tv->id));
    }

    public function test_google_feed_uses_gtin_product_type_and_discount_label(): void
    {
        $tv = $this->television();

        $item = $this->item(file_get_contents(app(GoogleFeed::class)->generate()), $tv->id);
        $g = $item->children('http://base.google.com/ns/1.0');

        $this->assertSame('8806095123456', (string) $g->gtin);
        $this->assertSame('in_stock', (string) $g->availability);
        $this->assertSame('ტელევიზორი', (string) $g->product_type);
        $this->assertSame('-14%', (string) $g->custom_label_1);
        $this->assertSame('1000-2500', (string) $g->custom_label_3);
        $this->assertStringNotContainsString('utm_source', (string) $g->link);
    }

    public function test_feed_urls_serve_xml_and_the_command_rebuilds_them(): void
    {
        $tv = $this->television();

        $this->get('/feed/facebook.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=utf-8');

        $this->artisan('feed:generate', ['--google' => true])->assertSuccessful();

        Storage::disk('local')->assertExists('feeds/google.xml');
        $this->assertNotNull($this->item(Storage::disk('local')->get('feeds/google.xml'), $tv->id));
    }
}
