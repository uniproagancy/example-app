<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\Supplier;
use App\Models\SupplierAttributeMap;
use App\Models\SupplierCategoryMap;
use App\Models\SupplierStock;
use App\Services\Import\Drivers\Elite\BlockedByElite;
use App\Services\Import\Drivers\Elite\EliteClient;
use App\Services\Import\ImportManager;
use App\Services\Import\SupplierActions;
use App\Support\Catalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeSupplierDriver;
use Tests\TestCase;

class ImportPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function supplier(string $code = 'fake', array $config = [], ?array $markup = null): Supplier
    {
        return Supplier::query()->create([
            'code' => $code,
            'name' => ucfirst($code),
            'driver' => FakeSupplierDriver::class,
            'is_active' => true,
            'priority' => 10,
            'config' => $config,
            'markup' => $markup ?? [['up_to' => 500, 'percent' => 20], ['add' => 150]],
        ]);
    }

    protected function import(Supplier $supplier, array $payloads): void
    {
        FakeSupplierDriver::$payloads[$supplier->code] = collect($payloads)->keyBy('externalId')->all();

        app(ImportManager::class)->run($supplier->fresh());
    }

    protected function setUp(): void
    {
        parent::setUp();

        FakeSupplierDriver::$payloads = [];
        Storage::fake('public');
    }

    public function test_new_product_arrives_as_a_draft_with_offer_translations_specs_and_queued_mappings(): void
    {
        $supplier = $this->supplier();

        $this->import($supplier, [FakeSupplierDriver::payload('1')]);

        $product = Product::query()->where('sku', 'SKU-1')->with(['translations', 'specs.translations', 'attributeValues'])->sole();

        $this->assertFalse($product->is_active);
        $this->assertNull($product->category_id);
        $this->assertSame('120.00', $product->price);
        $this->assertSame('100.00', $product->cost_price);
        $this->assertSame(5, $product->stock);
        $this->assertSame('Acme', $product->brand->name);
        $this->assertSame('მოკლე აღწერა', $product->translate('ka')->subtitle);
        $this->assertSame('satesto-telefoni-1', $product->translate('ka')->slug);
        $this->assertSame('Test phone 1', $product->translate('en')->name);

        $offer = ProductOffer::query()->sole();
        $this->assertSame('Phones > Smartphones', $offer->external_category);
        $this->assertSame('Acme', $offer->external_brand);

        $this->assertTrue(SupplierCategoryMap::query()->where('external_name', 'Phones > Smartphones')->whereNull('category_id')->exists());
        $this->assertSame(2, SupplierAttributeMap::query()->count());

        $memory = Attribute::query()->where('code', 'shida-mekhsiereba')->sole();
        $this->assertTrue($memory->is_filterable);
        $this->assertContains('256-gb', $product->attributeValues->pluck('code')->all());
        $this->assertSame(['shida-mekhsiereba' => ['256-gb']], Catalog::filterAttributes()->map(fn (array $attribute) => array_keys($attribute['values']))->all());
        $this->assertSame('256 GB', $product->specs->firstWhere('attribute_id', $memory->id)->translate('en')->value);

        $this->assertNull(Catalog::findBySku('SKU-1'));
    }

    public function test_mapping_a_category_and_relinking_puts_products_on_the_shelf(): void
    {
        $supplier = $this->supplier(config: ['publish_on_import' => true]);
        $smartphones = Category::query()->where('code', 'smartphones')->sole();

        $this->import($supplier, [FakeSupplierDriver::payload('1')]);

        SupplierCategoryMap::query()->where('external_name', 'Phones > Smartphones')->update(['category_id' => $smartphones->id]);

        $this->assertSame(1, app(SupplierActions::class)->relink());
        $this->assertSame($smartphones->id, Product::query()->where('sku', 'SKU-1')->value('category_id'));

        $this->import($supplier, [FakeSupplierDriver::payload('2')]);

        $published = Product::query()->where('sku', 'SKU-2')->sole();
        $this->assertSame($smartphones->id, $published->category_id);
        $this->assertTrue($published->is_active);
        $this->assertNotNull(Catalog::findBySku('SKU-2'));
    }

    public function test_rules_skip_items_without_stock_or_below_the_floor(): void
    {
        $supplier = $this->supplier(config: ['require_stock' => true, 'min_cost' => 50]);

        $this->import($supplier, [
            FakeSupplierDriver::payload('1', ['stock' => 0]),
            FakeSupplierDriver::payload('2', ['costPrice' => 20.0]),
            FakeSupplierDriver::payload('3'),
        ]);

        $this->assertSame(['SKU-3'], Product::query()->whereHas('offers')->pluck('sku')->all());
    }

    public function test_cheapest_offer_sets_the_price_and_locks_are_respected(): void
    {
        $expensive = $this->supplier('first', markup: [['add' => 10]]);
        $cheap = $this->supplier('second', markup: [['add' => 10]]);

        $this->import($expensive, [FakeSupplierDriver::payload('1', ['costPrice' => 300.0, 'stock' => 2])]);
        $this->import($cheap, [FakeSupplierDriver::payload('77', ['sku' => 'SKU-1', 'costPrice' => 250.0, 'stock' => 3])]);

        $product = Product::query()->where('sku', 'SKU-1')->sole();
        $this->assertSame(2, $product->offers()->count());
        $this->assertSame('260.00', $product->price);
        $this->assertSame(5, $product->stock);

        $phones = Category::query()->where('code', 'smartphones')->sole();
        $product->update(['price' => 999, 'price_lock' => true, 'category_id' => $phones->id, 'taxonomy_lock' => true]);
        SupplierCategoryMap::query()->update(['category_id' => Category::query()->where('code', 'laptops')->value('id')]);

        $this->import($cheap, [FakeSupplierDriver::payload('77', ['sku' => 'SKU-1', 'costPrice' => 100.0])]);

        $product->refresh();
        $this->assertSame('999.00', $product->price);
        $this->assertSame($phones->id, $product->category_id);
        $this->assertSame('100.00', $product->cost_price);
    }

    public function test_images_are_downloaded_checked_and_deduplicated(): void
    {
        Http::fake([
            '93.184.216.34/a.png' => Http::response(base64_decode(self::PNG), 200, ['Content-Type' => 'image/png']),
            '93.184.216.34/same.png' => Http::response(base64_decode(self::PNG), 200, ['Content-Type' => 'image/png']),
            '93.184.216.34/page.png' => Http::response('<html>not an image</html>', 200),
        ]);

        $supplier = $this->supplier();

        $this->import($supplier, [FakeSupplierDriver::payload('1', ['images' => [
            'https://93.184.216.34/a.png',
            'https://93.184.216.34/same.png',
            'https://93.184.216.34/page.png',
            'http://127.0.0.1/secret.png',
            'file:///etc/passwd',
        ]])]);

        $images = Product::query()->where('sku', 'SKU-1')->sole()->images;

        $this->assertCount(1, $images);
        $this->assertNotNull($images->first()->hash);
        Storage::disk('public')->assertExists($images->first()->path);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '127.0.0.1'));
    }

    public function test_price_list_loads_into_stock_rows_and_replaces_missing_ones(): void
    {
        $supplier = $this->supplier(config: ['columns' => ['key' => 'A', 'cost' => 'C', 'stock' => 'D', 'name' => 'B']]);
        $path = tempnam(sys_get_temp_dir(), 'pl').'.csv';

        file_put_contents($path, "code,name,cost,stock\nX1,Kettle,\"1,250.50\",4\nX2,Toaster,80,0\n");
        $result = app(SupplierActions::class)->loadPriceList($supplier, $path);

        $this->assertSame(2, $result['read']);
        $this->assertSame('1250.50', SupplierStock::query()->where('external_id', 'X1')->value('cost_price'));
        $this->assertSame('Kettle', SupplierStock::query()->where('external_id', 'X1')->value('data')['name'] ?? null);

        file_put_contents($path, "code,name,cost,stock\nX2,Toaster,80,7\n");
        $result = app(SupplierActions::class)->loadPriceList($supplier, $path);

        $this->assertSame(1, $result['zeroed']);
        $this->assertSame(0, SupplierStock::query()->where('external_id', 'X1')->value('quantity'));
        $this->assertSame(7, SupplierStock::query()->where('external_id', 'X2')->value('quantity'));

        @unlink($path);
    }

    public function test_elite_client_talks_to_its_own_api_and_reports_refusals(): void
    {
        config(['services.elite.api_url' => null]);
        Http::preventStrayRequests();

        $this->expectException(BlockedByElite::class);
        $this->expectExceptionMessage('ELITE_API_URL');

        (new EliteClient)->product('5');
    }

    public function test_elite_client_does_not_route_around_a_refusal(): void
    {
        config(['services.elite.api_url' => 'https://api.elite.test/product', 'services.elite.token' => 'secret']);
        Http::fake(['api.elite.test/*' => Http::response('Forbidden', 403)]);

        try {
            (new EliteClient)->product('5');
            $this->fail('A refusal must stop the import.');
        } catch (BlockedByElite $e) {
            $this->assertStringContainsString('HTTP 403', $e->getMessage());
        }

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer secret')
            && str_starts_with($request->header('User-Agent')[0], 'ELIO.GE importer'));
        Http::assertSentCount(2);
    }

    public function test_import_commands_run(): void
    {
        $supplier = $this->supplier();
        $this->import($supplier, [FakeSupplierDriver::payload('1')]);

        $this->artisan('import:run', ['supplier' => 'fake'])->assertSuccessful();
        $this->artisan('import:report', ['supplier' => 'fake'])->assertSuccessful();
        $this->artisan('attributes:doctor')->assertSuccessful();
    }
}
