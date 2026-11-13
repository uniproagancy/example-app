<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrdersExport;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrdersExportTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function order(string $number, string $status, string $createdAt, array $attributes = []): Order
    {
        $order = Order::query()->create([
            'number' => $number, 'status' => $status, 'first_name' => 'Nino', 'last_name' => 'Beridze', 'phone' => '555123456',
            'email' => 'nino@example.com', 'shipping_method' => 'courier', 'city' => 'tbilisi', 'address' => 'Chavchavadze 1',
            'apartment' => 'ბ. 4', 'payment_method' => 'cash', 'subtotal' => 100, 'discount' => 0, 'shipping' => 5, 'total' => 105,
            'locale' => 'ka', ...$attributes,
        ]);
        $order->forceFill(['created_at' => $createdAt])->save();
        $order->items()->create(['name' => 'Kettle', 'options' => ['ფერი' => 'Black'], 'unit_price' => 50, 'quantity' => 2, 'total' => 100]);

        return $order;
    }

    public function test_rows_hold_what_the_courier_needs(): void
    {
        $this->order('EL-000001', 'received', '2026-11-10 10:00:00');
        $this->order('EL-000002', 'received', '2026-11-10 11:00:00', ['payment_status' => Order::PAYMENT_PAID]);

        $sheet = app(OrdersExport::class)->spreadsheet(Order::query())->getActiveSheet()->toArray(null, true, false);

        $this->assertSame(__('admin.orders.export.to_collect'), $sheet[0][13]);
        $this->assertSame(
            ['EL-000001', '10.11.2026 10:00', 'ნანახი შეკვეთა', 'Nino Beridze', '555123456', __('checkout.cities.tbilisi'), 'Chavchavadze 1', 'ბ. 4'],
            array_slice($sheet[1], 0, 8),
        );
        $this->assertSame('Kettle (Black) × 2', $sheet[1][9]);
        $this->assertEquals([2, 5, 105, 105], array_slice($sheet[1], 10, 4));
        $this->assertEquals(0, $sheet[2][13]);
        $this->assertEquals([210, 105], [$sheet[3][12], $sheet[3][13]]);
    }

    public function test_the_table_exports_only_the_filtered_orders(): void
    {
        $this->order('EL-000001', 'received', '2026-11-10 10:00:00');
        $this->order('EL-000002', 'cancelled', '2026-11-10 12:00:00');
        $this->order('EL-000003', 'received', '2026-11-12 09:00:00');

        $component = Livewire::test(ListOrders::class)
            ->filterTable('status', 'received')
            ->filterTable('created_at', ['from' => '2026-11-10', 'until' => '2026-11-10']);

        $numbers = $component->instance()->getFilteredTableQuery()->pluck('number')->all();
        $this->assertSame(['EL-000001'], $numbers);

        $component->callTableAction('export')->assertFileDownloaded();
    }

    public function test_selected_orders_can_be_exported(): void
    {
        $first = $this->order('EL-000001', 'received', '2026-11-10 10:00:00');
        $this->order('EL-000002', 'received', '2026-11-10 12:00:00');

        Livewire::test(ListOrders::class)
            ->callTableBulkAction('export', [$first])
            ->assertFileDownloaded();
    }
}
