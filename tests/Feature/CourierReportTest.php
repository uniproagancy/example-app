<?php

namespace Tests\Feature;

use App\Filament\Courier\Pages\Deliveries;
use App\Filament\Courier\Pages\Report;
use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourierReportTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function order(array $attributes): Order
    {
        $order = Order::query()->create([
            'number' => 'EL-'.random_int(100000, 999999), 'status' => 'confirmed', 'first_name' => 'ნინო', 'phone' => '555123456',
            'payment_method' => 'cash', 'payment_status' => Order::PAYMENT_PENDING,
            'subtotal' => 100, 'discount' => 0, 'shipping' => 5, 'total' => 105, 'locale' => 'ka',
        ]);
        $order->forceFill($attributes)->save();

        return $order;
    }

    public function test_courier_sees_own_completed_and_returned_orders_and_cash_split_by_products_and_delivery(): void
    {
        $courier = User::factory()->courier()->create();
        $other = User::factory()->courier()->create();

        $this->order(['status' => 'completed', 'courier_id' => $courier->id, 'completed_at' => now()]);
        $this->order(['status' => 'completed', 'courier_id' => $courier->id, 'completed_at' => now(), 'subtotal' => 200, 'shipping' => 10, 'total' => 210]);
        $this->order(['status' => 'completed', 'courier_id' => $courier->id, 'completed_at' => now(), 'payment_method' => 'card', 'payment_status' => Order::PAYMENT_PAID, 'total' => 999]);
        $this->order(['status' => 'returned', 'courier_id' => $courier->id, 'returned_at' => now()]);
        $this->order(['status' => 'completed', 'courier_id' => $courier->id, 'completed_at' => now()->subDays(40), 'total' => 500]);
        $this->order(['status' => 'completed', 'courier_id' => $other->id, 'completed_at' => now(), 'total' => 700, 'shipping' => 0]);

        $this->actingAs($courier)->get(Report::getUrl(panel: 'courier'))->assertOk()->assertSee(__('courier.report.title'));

        Filament::setCurrentPanel(Filament::getPanel('courier'));
        $this->actingAs($courier);

        $today = Livewire::test(Report::class)->instance()->summary;
        $this->assertSame(3, $today['completed']);
        $this->assertSame(1, $today['returned']);
        $this->assertSame(2, $today['cash_orders']);
        $this->assertSame(300.0, $today['products']);
        $this->assertSame(15.0, $today['delivery']);
        $this->assertSame(315.0, $today['total']);

        $this->assertSame(4, Livewire::test(Report::class)->set('period', 'custom')->set('from', now()->subDays(45)->toDateString())->set('to', now()->toDateString())->instance()->summary['completed']);

        $this->actingAs(User::factory()->admin()->create());
        $everyone = Livewire::test(Report::class)->instance()->summary;
        $this->assertSame(4, $everyone['completed']);
        $this->assertSame(1015.0, $everyone['total']);
        $this->assertSame(1, Livewire::test(Report::class)->set('courier', (string) $other->id)->instance()->summary['completed']);
    }

    public function test_courier_can_return_an_order_that_the_buyer_refused(): void
    {
        $courier = User::factory()->courier()->create(['name' => 'გიორგი']);
        $this->actingAs($courier);
        Filament::setCurrentPanel(Filament::getPanel('courier'));

        $refused = $this->order([]);
        $delivered = $this->order([]);

        Livewire::test(Deliveries::class)
            ->assertSee(__('courier.deliveries.mark_returned'))
            ->call('markReturned', $refused->id)
            ->assertNotified(__('courier.deliveries.returned', ['number' => $refused->number]))
            ->call('markDelivered', $delivered->id);

        $refused->refresh();
        $this->assertSame('returned', $refused->status);
        $this->assertSame($courier->id, $refused->courier_id);
        $this->assertNotNull($refused->returned_at);
        $this->assertTrue($refused->isLost());
        $this->assertTrue($refused->events()->where('to', 'returned')->where('note', 'like', '%გიორგი%')->exists());
        $this->assertSame($courier->id, $delivered->fresh()->courier_id);
    }
}
