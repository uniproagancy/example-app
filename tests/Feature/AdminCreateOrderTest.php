<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Catalog;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class AdminCreateOrderTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->operator()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_product_search_does_not_load_the_whole_catalog(): void
    {
        $speaker = Product::query()->where('sku', 'jbl')->first();
        Catalog::flush();

        $found = CreateOrder::searchProducts('jbl');

        $this->assertArrayHasKey($speaker->id, $found);
        $this->assertStringContainsString(Catalog::money(Catalog::find($speaker->id)['price']), $found[$speaker->id]);
        $this->assertSame((float) Catalog::find($speaker->id)['price'], CreateOrder::currentPrice($speaker->id));
        $this->assertFalse(Cache::has(Catalog::CACHE_KEY));
    }

    public function test_operator_creates_a_phone_order_with_products(): void
    {
        $speaker = Product::query()->where('sku', 'jbl')->first();
        $price = (float) Catalog::find($speaker->id)['price'];

        Livewire::test(ListOrders::class)->assertActionExists('create');

        $this->assertNotEmpty(CreateOrder::searchProducts('jbl'));

        Livewire::test(CreateOrder::class)
            ->fillForm([
                'first_name' => 'ლევანი',
                'last_name' => 'გელაშვილი',
                'phone' => '555 98 76 54',
                'address' => 'გორგილაძე 5, ბათუმი',
                'is_region' => true,
                'payment_method' => 'card',
                'products' => [
                    ['product_id' => $speaker->id, 'quantity' => 2, 'price' => $price - 10],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(OrderResource::getUrl('view', ['record' => Order::query()->sole()]));

        $order = Order::query()->with('items')->sole();

        $this->assertSame('admin', $order->source);
        $this->assertSame('received', $order->status);
        $this->assertSame('card', $order->payment_method);
        $this->assertTrue($order->is_region);
        $this->assertNull($order->city);
        $this->assertSame('გორგილაძე 5, ბათუმი', $order->address);
        $this->assertEquals(($price - 10) * 2, (float) $order->subtotal);
        $this->assertEquals((float) $order->subtotal + (float) $order->shipping, (float) $order->total);
        $this->assertSame(2, $order->items->sole()->quantity);
        $this->assertSame($speaker->id, $order->items->sole()->product_id);
    }

    public function test_free_shipping_and_required_products(): void
    {
        $speaker = Product::query()->where('sku', 'jbl')->first();

        Livewire::test(CreateOrder::class)
            ->fillForm([
                'first_name' => 'ლევანი',
                'phone' => '555987654',
                'payment_method' => 'cash',
                'products' => [],
            ])
            ->call('create')
            ->assertHasFormErrors(['products']);

        Livewire::test(CreateOrder::class)
            ->fillForm([
                'first_name' => 'ლევანი',
                'phone' => '555987654',
                'payment_method' => 'cash',
                'free_shipping' => true,
                'products' => [['product_id' => $speaker->id, 'quantity' => 1, 'price' => 20]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('0.00', Order::query()->sole()->shipping);
    }
}
