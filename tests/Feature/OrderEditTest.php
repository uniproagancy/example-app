<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\User;
use App\Support\Catalog;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderEditTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function order(array $attributes = []): Order
    {
        $iphone = Product::query()->where('sku', 'ip16')->sole();

        $order = Order::query()->create([
            'number' => 'EL-123456', 'status' => 'new', 'source' => 'checkout', 'first_name' => 'Nino', 'last_name' => 'Beridze',
            'phone' => '555000000', 'email' => 'nino@example.com', 'shipping_method' => 'courier', 'city' => 'tbilisi',
            'address' => 'Chavchavadze 1', 'payment_method' => 'cash', 'subtotal' => 1000, 'discount' => 0, 'shipping' => 0,
            'total' => 1000, 'locale' => 'ka', ...$attributes,
        ]);

        $order->items()->create(['product_id' => $iphone->id, 'name' => 'iPhone 16 Pro', 'unit_price' => 1000, 'quantity' => 1, 'total' => 1000]);

        return $order;
    }

    public function test_orders_list_drops_source_city_and_product_count_columns(): void
    {
        $order = $this->order();

        Livewire::test(ListOrders::class)
            ->assertCanSeeTableRecords([$order])
            ->assertTableColumnExists('number')
            ->assertTableColumnDoesNotExist('source')
            ->assertTableColumnDoesNotExist('city')
            ->assertTableColumnDoesNotExist('items_count');

        $this->get(OrderResource::getUrl('view', ['record' => $order]))
            ->assertOk()
            ->assertSee(__('admin.orders.title', ['number' => 'EL-123456']))
            ->assertSee('iPhone 16 Pro')
            ->assertSee(__('admin.orders.history'));
    }

    public function test_products_customer_and_delivery_are_edited_right_on_the_order(): void
    {
        $order = $this->order();
        $item = $order->items()->sole();
        $galaxy = Product::query()->where('sku', 's25')->sole();
        $galaxyPrice = (float) (Catalog::find($galaxy->id)['price'] ?? $galaxy->price);

        $page = Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->set('productSearch', 's25');

        $this->assertSame([$galaxy->id], $page->instance()->foundProducts->pluck('id')->all());

        $page->call('add', $galaxy->id)
            ->assertSet('productSearch', '')
            ->call('increment', 'i'.$item->id)
            ->set('customer.phone', '599 11 22 33')
            ->set('delivery.city', 'batumi')
            ->set('delivery.address', 'Rustaveli 5')
            ->set('shippingFee', '12.50')
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotified(__('admin.orders.saved'));

        $order->refresh();
        $this->assertSame(2, $order->items()->count());
        $this->assertSame(2, $order->items()->whereKey($item->id)->value('quantity'));
        $this->assertSame(1, $order->items()->where('product_id', $galaxy->id)->value('quantity'));
        $this->assertSame('599 11 22 33', $order->phone);
        $this->assertSame('batumi', $order->city);
        $this->assertSame('Rustaveli 5', $order->address);
        $this->assertEquals(2000 + $galaxyPrice, (float) $order->subtotal);
        $this->assertEquals(12.5, (float) $order->shipping);
        $this->assertEquals(2000 + $galaxyPrice + 12.5, (float) $order->total);

        $event = $order->events()->where('type', '!=', 'opened')->sole();
        $this->assertSame('edit', $event->type);
        $this->assertSame($this->admin->id, $event->user_id);
        $this->assertStringContainsString(__('admin.orders.changed.items'), $event->note);
        $this->assertStringContainsString(__('admin.orders.changed.customer'), $event->note);
        $this->assertStringContainsString(__('admin.orders.changed.delivery'), $event->note);
    }

    public function test_lines_can_be_removed_and_the_promo_discount_follows_the_new_subtotal(): void
    {
        PromoCode::query()->create(['code' => 'SALE10', 'percent' => 10, 'is_active' => true]);
        $order = $this->order(['promo_code' => 'SALE10', 'discount' => 100, 'total' => 900]);
        $item = $order->items()->sole();

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->call('increment', 'i'.$item->id)
            ->call('increment', 'i'.$item->id)
            ->call('decrement', 'i'.$item->id)
            ->assertSet('totals.discount', 200.0)
            ->call('save')
            ->assertHasNoErrors()
            ->call('remove', 'i'.$item->id)
            ->call('save')
            ->assertHasErrors(['lines']);

        $order->refresh();
        $this->assertSame(2, $order->items()->sole()->quantity);
        $this->assertEquals(200, (float) $order->discount);
        $this->assertEquals(1800, (float) $order->total);
    }

    public function test_shipping_is_recalculated_by_the_shop_rule_on_request(): void
    {
        $order = $this->order(['shipping' => 33]);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertSet('shippingFee', '33.00')
            ->call('recalculateShipping')
            ->assertSet('shippingFee', '0.00')
            ->set('shippingFee', '12.00')
            ->call('recalculateShipping')
            ->assertSet('shippingFee', '0.00');
    }

    public function test_bad_input_and_unchanged_saves_leave_no_trace(): void
    {
        $order = $this->order();

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->call('save')
            ->assertNotified(__('admin.orders.nothing_changed'))
            ->set('customer.email', 'not-an-email')
            ->set('customer.first_name', '')
            ->call('save')
            ->assertHasErrors(['customer.email', 'customer.first_name']);

        $this->assertSame(['opened'], $order->events()->pluck('type')->all());
        $this->assertSame('nino@example.com', $order->fresh()->email);
    }

    public function test_closed_orders_only_take_status_changes_and_notes(): void
    {
        $order = $this->order(['status' => 'delivered']);
        $item = $order->items()->sole();

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertSee(__('admin.orders.closed'))
            ->call('increment', 'i'.$item->id)
            ->call('add', Product::query()->where('sku', 's25')->value('id'))
            ->assertSet('lines.i'.$item->id.'.quantity', 1)
            ->assertCount('lines', 1)
            ->call('save')
            ->assertNotified(__('admin.orders.closed'))
            ->set('comment', 'კლიენტმა დაგვირეკა')
            ->call('addNote')
            ->assertSet('comment', '')
            ->set('status', 'confirmed')
            ->call('changeStatus');

        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame(['status', 'note', 'opened'], $order->events()->pluck('type')->all());
        $this->assertSame('კლიენტმა დაგვირეკა', $order->events()->where('type', 'note')->value('note'));
    }
}
