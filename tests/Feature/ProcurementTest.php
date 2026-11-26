<?php

namespace Tests\Feature;

use App\Filament\Courier\Pages\Pickups;
use App\Filament\Pages\OrderedProducts;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProcurementTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function item(string $name, string $status = 'confirmed'): OrderItem
    {
        $order = Order::query()->create([
            'number' => 'EL-'.random_int(100000, 999999), 'status' => $status, 'first_name' => 'ნინო', 'phone' => '555123456',
            'payment_method' => 'cash', 'subtotal' => 100, 'discount' => 0, 'shipping' => 0, 'total' => 100, 'locale' => 'ka',
        ]);

        return $order->items()->create([
            'product_id' => Product::query()->where('sku', 'jbl')->value('id'),
            'name' => $name, 'unit_price' => 100, 'quantity' => 1, 'total' => 100,
        ]);
    }

    public function test_admin_marks_items_ordered_from_the_supplier_and_sets_where_to_pick_them_up(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $speaker = $this->item('JBL Speaker');
        $kettle = $this->item('Kettle');
        $iron = $this->item('Iron');

        Livewire::test(OrderedProducts::class)
            ->assertSee(__('admin.ordered_products.to_order'))
            ->callTableAction('markOrdered', $speaker)
            ->callTableAction('pickup', $kettle, data: [
                'pickup_warehouse' => 'Zoommer — ვაკე',
                'pickup_address' => 'ჭავჭავაძის 50',
                'pickup_note' => 'ჰკითხე ნიკას',
                'ordered' => true,
            ])
            ->filterTable('procurement', 'to_order')
            ->assertCanSeeTableRecords([$iron])
            ->assertCanNotSeeTableRecords([$speaker, $kettle]);

        $speaker->refresh();
        $this->assertNotNull($speaker->supplier_ordered_at);
        $this->assertSame($admin->id, $speaker->supplier_ordered_by);

        $kettle->refresh();
        $this->assertSame('Zoommer — ვაკე', $kettle->pickup_warehouse);
        $this->assertSame('ჭავჭავაძის 50', $kettle->pickup_address);
        $this->assertSame('ჰკითხე ნიკას', $kettle->pickup_note);
        $this->assertNotNull($kettle->supplier_ordered_at);

        Livewire::test(OrderedProducts::class)->callTableBulkAction('markOrderedBulk', [$iron]);
        $this->assertNotNull($iron->fresh()->supplier_ordered_at);

        Livewire::test(OrderedProducts::class)->callTableAction('unmarkOrdered', $iron);
        $this->assertNull($iron->fresh()->supplier_ordered_at);
    }

    public function test_courier_sees_the_days_pickups_by_warehouse_and_ticks_what_was_picked_up(): void
    {
        $courier = User::factory()->courier()->create(['name' => 'გიორგი']);

        $today = $this->item('JBL Speaker');
        $today->update(['supplier_ordered_at' => now(), 'pickup_warehouse' => 'Zoommer — ვაკე', 'pickup_address' => 'ჭავჭავაძის 50', 'pickup_note' => 'ჰკითხე ნიკას']);
        $yesterday = $this->item('Old Kettle');
        $yesterday->update(['supplier_ordered_at' => now()->subDay()]);
        $notOrdered = $this->item('Not ordered iron');
        $cancelled = $this->item('Cancelled toaster', 'cancelled');
        $cancelled->update(['supplier_ordered_at' => now()]);

        $this->actingAs($courier)->get(Pickups::getUrl(panel: 'courier'))->assertOk()->assertSee(__('courier.pickups.title'));

        Filament::setCurrentPanel(Filament::getPanel('courier'));

        $page = Livewire::test(Pickups::class)
            ->assertSee('JBL Speaker')
            ->assertSee('Zoommer — ვაკე')
            ->assertSee('ჭავჭავაძის 50')
            ->assertSee('ჰკითხე ნიკას')
            ->assertDontSee('Old Kettle')
            ->assertDontSee('Not ordered iron')
            ->assertDontSee('Cancelled toaster')
            ->assertSee(__('courier.pickups.progress', ['picked' => 0, 'total' => 1]));

        $page->call('togglePickup', $today->id)
            ->assertSee(__('courier.pickups.progress', ['picked' => 1, 'total' => 1]));

        $today->refresh();
        $this->assertNotNull($today->picked_up_at);
        $this->assertSame($courier->id, $today->picked_up_by);
        $this->assertTrue($today->order->events()->where('note', 'like', '%გიორგი%JBL Speaker%')->exists());

        Livewire::test(Pickups::class)->set('day', now()->subDay()->toDateString())->assertSee('Old Kettle')->assertDontSee('JBL Speaker');

        Livewire::test(Pickups::class)->call('togglePickup', $notOrdered->id)->assertForbidden();
        $this->assertNull($notOrdered->fresh()->picked_up_at);

        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(OrderedProducts::class)
            ->filterTable('procurement', 'picked')
            ->assertCanSeeTableRecords([$today])
            ->assertSee('გიორგი');
    }
}
