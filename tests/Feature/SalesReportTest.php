<?php

namespace Tests\Feature;

use App\Filament\Pages\Reports;
use App\Filament\Widgets\SalesChart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Orders\SalesReport;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function order(float $total, array $attributes = [], int $quantity = 1, string $sku = 'jbl'): Order
    {
        $product = Product::query()->where('sku', $sku)->sole();
        $order = Order::query()->create([
            'number' => 'EL-'.random_int(100000, 999999), 'status' => 'received', 'first_name' => 'A', 'phone' => '555000000',
            'payment_method' => 'cash', 'payment_status' => Order::PAYMENT_PENDING, 'city' => 'tbilisi',
            'subtotal' => $total, 'discount' => 0, 'shipping' => 0, 'total' => $total, 'locale' => 'ka',
            ...$attributes,
        ]);
        $order->items()->create(['product_id' => $product->id, 'name' => $product->sku.' item', 'unit_price' => $total / $quantity, 'quantity' => $quantity, 'total' => $total]);

        return $order;
    }

    public function test_summary_excludes_cancelled_orders_and_compares_periods(): void
    {
        $this->travelTo('2026-11-14 12:00');

        $this->order(100, quantity: 2);
        $this->order(300, ['payment_status' => Order::PAYMENT_PAID, 'source' => 'quick'], sku: 'ip16');
        $this->order(999, ['status' => 'cancelled']);
        $old = $this->order(200);
        $old->forceFill(['created_at' => now()->subDays(40)])->save();

        $report = SalesReport::forPeriod('30');
        $summary = $report->summary();

        $this->assertSame(3, $summary['orders']);
        $this->assertSame(2, $summary['valid']);
        $this->assertEquals(400, $summary['revenue']);
        $this->assertEquals(300, $summary['paid']);
        $this->assertEquals(200, $summary['average']);
        $this->assertSame(3, $summary['items']);
        $this->assertEquals(33.3, $summary['cancelled_share']);
        $this->assertEquals(100, $report->change($summary['revenue'], $report->previous()->summary()['revenue']));

        $this->assertSame('ip16 item', $report->topProducts()->first()['name']);
        $this->assertEquals(400, $report->daily()->firstWhere('day', '2026-11-14')['revenue']);
        $this->assertCount(30, $report->daily());
        $this->assertSame(['quick', 'checkout'], $report->breakdown('source')->sortBy('revenue', descending: true)->pluck('value')->values()->all());
        $this->assertEquals(400, $report->topCategories()->sum('revenue'));
    }

    public function test_custom_periods_are_ordered_and_bounded(): void
    {
        $report = SalesReport::forPeriod('custom', '2026-11-10', '2026-11-01');

        $this->assertSame('2026-11-01 00:00:00', $report->from->toDateTimeString());
        $this->assertSame('2026-11-10 23:59:59', $report->to->toDateTimeString());
        $this->assertSame('2026-10-22', $report->previous()->from->toDateString());
        $this->assertSame('2026-10-31', $report->previous()->to->toDateString());
    }

    public function test_admin_opens_the_reports_page_and_operators_cannot(): void
    {
        $this->order(150);
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->get(Reports::getUrl())->assertOk()->assertSee(__('admin.reports.top_products'));

        Livewire::test(Reports::class)
            ->assertSee('150 ₾')
            ->set('period', '7')
            ->assertSet('from', now()->subDays(6)->toDateString())
            ->set('from', now()->subDays(2)->toDateString())
            ->assertSet('period', 'custom');

        Livewire::test(SalesChart::class, ['from' => now()->subDays(6)->toDateString(), 'to' => now()->toDateString()])->assertOk();

        $this->actingAs(User::factory()->operator()->create());
        $this->get(Reports::getUrl())->assertForbidden();
    }
}
