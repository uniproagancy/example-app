<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Http\Controllers\OrderPrintController;
use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderPrintTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function order(array $attributes = []): Order
    {
        $order = Order::query()->create([
            'number' => 'EL-'.random_int(100000, 999999), 'status' => 'received', 'first_name' => 'ნინო', 'last_name' => 'ბერიძე', 'phone' => '555 12 34 56',
            'shipping_method' => 'courier', 'city' => 'tbilisi', 'address' => 'რუსთაველი 1', 'note' => 'დარეკეთ ადრე',
            'payment_method' => 'cash', 'payment_status' => Order::PAYMENT_PENDING,
            'subtotal' => 120, 'discount' => 0, 'shipping' => 0, 'total' => 120, 'locale' => 'ka',
            ...$attributes,
        ]);
        $order->items()->create(['name' => 'JBL Charge 6', 'options' => ['შავი'], 'unit_price' => 60, 'quantity' => 2, 'total' => 120]);

        return $order;
    }

    public function test_staff_print_delivery_slips_with_the_amount_to_collect(): void
    {
        $cash = $this->order();
        $paid = $this->order(['payment_status' => Order::PAYMENT_PAID, 'paid_at' => now()]);

        $this->actingAs(User::factory()->operator()->create())
            ->get(OrderPrintController::url([$paid->id, $cash->id]))
            ->assertOk()
            ->assertSeeInOrder([$paid->number, __('admin.print.already_paid'), $cash->number, '120 ₾'])
            ->assertSee('JBL Charge 6')
            ->assertSee('შავი')
            ->assertSee('თბილისი, რუსთაველი 1')
            ->assertSee('დარეკეთ ადრე')
            ->assertSee(__('admin.print.courier_signature'));
    }

    public function test_guests_and_customers_cannot_print(): void
    {
        $order = $this->order();

        $this->get(OrderPrintController::url([$order->id]))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(OrderPrintController::url([$order->id]))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('orders.print', ['ids' => 'abc']))->assertNotFound();
    }

    public function test_print_buttons_are_on_the_order_page_and_the_list(): void
    {
        $order = $this->order();
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionHasUrl('print', OrderPrintController::url([$order->id]));

        Livewire::test(ListOrders::class)
            ->callTableBulkAction('print', [$order])
            ->assertHasNoTableBulkActionErrors();
    }
}
