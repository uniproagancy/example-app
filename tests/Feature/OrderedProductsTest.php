<?php

namespace Tests\Feature;

use App\Filament\Pages\OrderedProducts;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderedProductsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function item(string $status, Product $product, string $createdAt, int $quantity = 1): OrderItem
    {
        $order = Order::query()->create([
            'number' => 'EL-'.random_int(100000, 999999), 'status' => $status, 'first_name' => 'Nino', 'phone' => '555123456',
            'payment_method' => 'cash', 'subtotal' => 100, 'discount' => 0, 'shipping' => 0, 'total' => 100, 'locale' => 'ka',
        ]);
        $order->forceFill(['created_at' => $createdAt])->save();

        return $order->items()->create(['product_id' => $product->id, 'name' => 'Item '.$product->sku, 'unit_price' => 100, 'quantity' => $quantity, 'total' => 100 * $quantity]);
    }

    public function test_confirmed_order_lines_are_listed_by_supplier_and_by_order_day(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $zoommer = Supplier::query()->create(['code' => 'zoommer-test', 'name' => 'Zoommer-test', 'driver' => 'none', 'is_active' => true, 'priority' => 10, 'config' => []]);
        $phone = Product::query()->where('sku', 'ip16')->sole();
        $speaker = Product::query()->where('sku', 'jbl')->sole();
        $phone->offers()->create(['supplier_id' => $zoommer->id, 'external_id' => 'Z-777', 'cost_price' => 900, 'stock' => 1]);

        $today = $this->item('received', $phone, now()->toDateTimeString(), 2);
        $older = $this->item('completed', $speaker, now()->subDays(10)->toDateTimeString());
        $cancelled = $this->item('cancelled', $phone, now()->toDateTimeString());
        $unconfirmed = $this->item('new', $speaker, now()->toDateTimeString());

        $this->get(OrderedProducts::getUrl())->assertOk()->assertSee(__('admin.ordered_products.label'))->assertSee('Zoommer-test');

        $page = Livewire::test(OrderedProducts::class)
            ->assertCanSeeTableRecords([$today, $older])
            ->assertCanNotSeeTableRecords([$cancelled, $unconfirmed])
            ->assertSee('Z-777')
            ->assertSee(__('admin.ordered_products.total_quantity'));

        $page->call('showSupplier', (string) $zoommer->id)
            ->assertCanSeeTableRecords([$today])
            ->assertCanNotSeeTableRecords([$older]);

        $page->call('showSupplier', OrderedProducts::MANUAL)
            ->assertCanSeeTableRecords([$older])
            ->assertCanNotSeeTableRecords([$today]);

        Livewire::test(OrderedProducts::class)
            ->filterTable('period', 'today')
            ->assertCanSeeTableRecords([$today])
            ->assertCanNotSeeTableRecords([$older]);

        Livewire::test(OrderedProducts::class)
            ->filterTable('status', ['new'])
            ->assertCanSeeTableRecords([$unconfirmed])
            ->assertCanNotSeeTableRecords([$today, $older, $cancelled]);
    }

    public function test_operators_cannot_open_the_page(): void
    {
        $this->actingAs(User::factory()->operator()->create());

        $this->get(OrderedProducts::getUrl())->assertForbidden();
    }
}
