<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\Supplier;
use App\Services\Import\Drivers\Zoommer\ZoommerDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeSupplierDriver;
use Tests\TestCase;

class ImportPushTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected const TOKEN = 'push-token-for-tests-0123456789abcdef';

    protected const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        config(['import.push_token' => self::TOKEN]);
        Storage::fake('public');
        Http::preventStrayRequests();
        Supplier::query()->where('code', 'zoommer')->update(['is_active' => true]);
    }

    protected function payload(string $id = '54835'): string
    {
        return json_encode(FakeSupplierDriver::payload($id, [
            'images' => ['https://s3.zoommer.ge/site/a.jpeg', 'https://s3.zoommer.ge/site/b.jpeg'],
        ]));
    }

    public function test_the_door_is_closed_without_a_token_or_with_a_wrong_one(): void
    {
        $this->getJson('/import-push/zoommer', ['Authorization' => 'Bearer wrong'])->assertUnauthorized();

        config(['import.push_token' => null]);

        $this->getJson('/import-push/zoommer', ['Authorization' => 'Bearer '.self::TOKEN])->assertNotFound();
    }

    public function test_only_allowed_active_suppliers_can_be_pushed(): void
    {
        $this->withToken(self::TOKEN)->getJson('/import-push/alta')->assertNotFound();

        Supplier::query()->where('code', 'zoommer')->update(['is_active' => false]);

        $this->withToken(self::TOKEN)->getJson('/import-push/zoommer')->assertNotFound();
    }

    public function test_the_runner_learns_the_scan_settings_and_known_ids(): void
    {
        $this->withToken(self::TOKEN)->getJson('/import-push/zoommer')
            ->assertOk()
            ->assertJson([
                'code' => 'zoommer',
                'driver' => ZoommerDriver::class,
                'config' => ['from' => 1, 'to' => 1000, 'stock_units' => 5],
                'known_ids' => [],
            ])
            ->assertJsonMissingPath('config.require_stock');
    }

    public function test_a_new_product_asks_for_its_images_then_is_saved_with_them(): void
    {
        $this->withToken(self::TOKEN)
            ->postJson('/import-push/zoommer', ['payload' => $this->payload()])
            ->assertOk()
            ->assertJson(['status' => 'needs_images']);

        $this->assertFalse(Product::query()->where('sku', 'like', 'SKU-%')->exists());

        $this->withToken(self::TOKEN)
            ->post('/import-push/zoommer', [
                'payload' => $this->payload(),
                'images' => [
                    0 => UploadedFile::fake()->createWithContent('a.png', base64_decode(self::PNG)),
                    1 => UploadedFile::fake()->createWithContent('b.png', base64_decode(self::PNG)),
                ],
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['status' => 'saved', 'images' => 1]);

        $product = Product::query()->where('sku', 'SKU-54835')->sole();
        $offer = ProductOffer::query()->where('external_id', '54835')->sole();

        $this->assertSame('54835', $offer->external_id);
        $this->assertSame($product->id, $offer->product_id);
        Storage::disk('public')->assertExists($product->images()->sole()->path);
    }

    public function test_a_known_product_with_its_images_is_updated_without_sending_them_again(): void
    {
        $this->withToken(self::TOKEN)->post('/import-push/zoommer', [
            'payload' => json_encode(FakeSupplierDriver::payload('7', ['images' => ['https://s3.zoommer.ge/site/a.jpeg']])),
            'images' => [UploadedFile::fake()->createWithContent('a.png', base64_decode(self::PNG))],
        ], ['Accept' => 'application/json'])->assertJson(['status' => 'saved']);

        $this->withToken(self::TOKEN)
            ->postJson('/import-push/zoommer', ['payload' => json_encode(FakeSupplierDriver::payload('7', [
                'costPrice' => 250.0,
                'images' => ['https://s3.zoommer.ge/site/a.jpeg'],
            ]))])
            ->assertOk()
            ->assertJson(['status' => 'saved', 'images' => 1]);

        $this->assertSame('250.00', ProductOffer::query()->where('external_id', '7')->sole()->cost_price);
        $this->assertSame(['7'], $this->withToken(self::TOKEN)->getJson('/import-push/zoommer')->json('known_ids'));
    }

    public function test_a_broken_payload_is_refused(): void
    {
        $this->withToken(self::TOKEN)
            ->postJson('/import-push/zoommer', ['payload' => json_encode(['externalId' => '1'])])
            ->assertUnprocessable();

        $this->assertFalse(Product::query()->where('sku', 'like', 'SKU-%')->exists());
    }
}
