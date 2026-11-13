<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\OrderPixelData;
use App\Models\Product;
use App\Models\User;
use App\Services\Facebook\Pixel;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PixelTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1, 'fbtrace_id' => 'trace'])]);
    }

    protected function enable(): void
    {
        Http::preventStrayRequests();
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1, 'fbtrace_id' => 'trace'])]);

        config([
            'services.facebook.pixel_id' => '123456789',
            'services.facebook.access_token' => 'test-token',
            'services.facebook.api_version' => 'v24.0',
            'services.facebook.test_event_code' => null,
            'services.facebook.queue_events' => false,
        ]);
    }

    protected function events(string $name): array
    {
        return collect(Http::recorded())
            ->map(fn (array $pair) => $pair[0])
            ->filter(fn (Request $request) => str_contains($request->url(), 'graph.facebook.com/v24.0/123456789/events'))
            ->flatMap(fn (Request $request) => $request->data()['data'] ?? [])
            ->where('event_name', $name)
            ->values()
            ->all();
    }

    protected function order(array $attributes = []): Order
    {
        $iphone = Product::query()->where('sku', 'ip16')->sole();

        $order = Order::query()->create([
            'number' => 'EL-654321', 'status' => 'new', 'source' => 'checkout', 'first_name' => 'Nino', 'last_name' => 'Beridze',
            'phone' => '555 12 34 56', 'email' => 'Nino@Example.com', 'shipping_method' => 'courier', 'city' => 'tbilisi',
            'address' => 'Chavchavadze 1', 'payment_method' => 'cash', 'payment_status' => Order::PAYMENT_PENDING,
            'subtotal' => 1000, 'discount' => 0, 'shipping' => 0, 'total' => 1000, 'locale' => 'ka', ...$attributes,
        ]);

        $order->items()->create(['product_id' => $iphone->id, 'name' => 'iPhone 16 Pro', 'unit_price' => 1000, 'quantity' => 1, 'total' => 1000]);

        return $order;
    }

    public function test_nothing_is_loaded_or_sent_until_the_pixel_is_configured(): void
    {
        $this->refreshApplicationWithLocale('ka');
        $this->get('/ka')->assertOk()->assertDontSee("fbq('init'", false);

        Http::assertNothingSent();
    }

    public function test_page_view_is_sent_from_the_server_with_the_same_event_id_as_the_browser(): void
    {
        $this->refreshApplicationWithLocale('ka');
        $this->enable();

        $response = $this->get('/ka')->assertOk()->assertSee("fbq('init'", false);

        $pageViews = $this->events('PageView');
        $this->assertCount(1, $pageViews);
        $this->assertStringContainsString('pageViewId:"'.$pageViews[0]['event_id'].'"', str_replace(' ', '', $response->getContent()));
        $this->assertSame(hash('sha256', 'ge'), $pageViews[0]['user_data']['country']);
        $this->assertArrayHasKey('external_id', $pageViews[0]['user_data']);
        $this->assertNotNull($response->getCookie('_fbp', false));
        $this->assertNotNull($response->getCookie(Pixel::VISITOR_COOKIE, false));
    }

    public function test_admins_are_never_tracked(): void
    {
        $this->refreshApplicationWithLocale('ka');
        $this->enable();
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/ka')->assertOk()->assertDontSee("fbq('init'", false);

        Http::assertNothingSent();
    }

    public function test_product_page_sends_view_content(): void
    {
        $this->refreshApplicationWithLocale('ka');
        $this->enable();
        $iphone = Product::query()->where('sku', 'ip16')->sole();

        $this->get('/ka/product/apple-iphone-16-pro')->assertOk();

        $views = $this->events('ViewContent');
        $this->assertCount(1, $views);
        $this->assertSame([(string) $iphone->id], $views[0]['custom_data']['content_ids']);
        $this->assertSame('GEL', $views[0]['custom_data']['currency']);
    }

    public function test_a_sale_is_reported_once_with_hashed_customer_details(): void
    {
        $this->enable();
        $order = $this->order();
        app(Pixel::class)->snapshotForOrder($order);

        $this->assertSame([], app(Pixel::class)->browserPurchase($order));
        $this->assertCount(0, $this->events('Purchase'));

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->set('paymentStatus', Order::PAYMENT_PAID)
            ->call('changePaymentStatus')
            ->set('status', 'delivered')
            ->call('changeStatus');

        $purchases = $this->events('Purchase');
        $this->assertCount(1, $purchases);
        $this->assertSame('EL-654321', $purchases[0]['custom_data']['order_id']);
        $this->assertEquals(1000, $purchases[0]['custom_data']['value']);
        $this->assertSame(hash('sha256', 'nino@example.com'), $purchases[0]['user_data']['em']);
        $this->assertSame(hash('sha256', '995555123456'), $purchases[0]['user_data']['ph']);
        $this->assertSame(hash('sha256', 'nino'), $purchases[0]['user_data']['fn']);
        $this->assertSame(hash('sha256', 'beridze'), $purchases[0]['user_data']['ln']);
        $this->assertNotNull(OrderPixelData::query()->where('order_id', $order->id)->value('sent_at'));
        $this->assertTrue($order->events()->where('note', __('admin.orders.purchase_reported'))->exists());
    }

    public function test_order_page_fires_the_browser_purchase_only_once(): void
    {
        $this->enable();
        $order = $this->order(['payment_status' => Order::PAYMENT_PAID, 'paid_at' => now()]);
        app(Pixel::class)->snapshotForOrder($order);
        $order->rememberInSession();

        $first = app(Pixel::class)->browserPurchase($order);
        $second = app(Pixel::class)->browserPurchase($order);

        $this->assertSame('Purchase', $first['event']);
        $this->assertSame(OrderPixelData::query()->where('order_id', $order->id)->value('event_id'), $first['id']);
        $this->assertSame([], $second);
        $this->assertCount(1, $this->events('Purchase'));
        $this->assertSame($first['id'], $this->events('Purchase')[0]['event_id']);
    }

    public function test_cancelled_orders_are_not_sales_and_edits_refresh_the_frozen_copy(): void
    {
        $this->enable();
        $pixel = app(Pixel::class);
        $order = $this->order(['status' => 'cancelled', 'payment_status' => Order::PAYMENT_PAID]);
        $snapshot = $pixel->snapshotForOrder($order);

        $this->assertFalse($pixel->isSale($order));
        $this->assertFalse($pixel->reportPurchase($order));

        $snapshot->forceFill(['custom_data' => ['value' => 1], 'occurred_at' => now()])->save();
        $this->assertTrue($pixel->refreshAfterEdit($order));
        $this->assertNull($snapshot->fresh()->custom_data);
    }

    public function test_a_failing_meta_api_never_breaks_the_page(): void
    {
        $this->refreshApplicationWithLocale('ka');
        $this->enable();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'down']], 500)]);

        $this->get('/ka/product/apple-iphone-16-pro')->assertOk();
    }

    public function test_test_command_reports_missing_settings(): void
    {
        $this->artisan('pixel:test')->assertFailed();

        $this->enable();
        $this->artisan('pixel:test')->assertSuccessful();
        $this->assertCount(1, $this->events('ViewContent'));
    }
}
