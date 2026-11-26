<?php

namespace Tests\Feature;

use App\Filament\Courier\Pages\Deliveries;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CourierDeliveriesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function item(string $status): OrderItem
    {
        $order = Order::query()->create([
            'number' => 'EL-'.random_int(100000, 999999), 'status' => $status, 'first_name' => 'ნინო', 'last_name' => 'ბერიძე',
            'phone' => '555 12 34 56', 'address' => 'რუსთაველის 1', 'payment_method' => 'cash', 'payment_status' => Order::PAYMENT_PENDING,
            'subtotal' => 100, 'discount' => 0, 'shipping' => 0, 'total' => 100, 'locale' => 'ka',
        ]);

        return $order->items()->create([
            'product_id' => Product::query()->where('sku', 'jbl')->value('id'),
            'name' => 'JBL Charge '.$status, 'unit_price' => 100, 'quantity' => 1, 'total' => 100,
        ]);
    }

    public function test_couriers_only_reach_the_deliveries_page(): void
    {
        $courier = User::factory()->courier()->create();

        $this->actingAs($courier)->get(Deliveries::getUrl(panel: 'courier'))->assertOk()->assertSee(__('courier.deliveries.title'));
        $this->actingAs($courier)->get('/admin')->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())->get(Deliveries::getUrl(panel: 'courier'))->assertOk();
        $this->actingAs(User::factory()->operator()->create())->get(Deliveries::getUrl(panel: 'courier'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(Deliveries::getUrl(panel: 'courier'))->assertForbidden();
    }

    public function test_courier_records_the_serial_number_warranty_and_a_photo_for_each_item(): void
    {
        Storage::fake('local');
        $courier = User::factory()->courier()->create(['name' => 'გიორგი']);
        $this->actingAs($courier);
        Filament::setCurrentPanel(Filament::getPanel('courier'));

        $confirmed = $this->item('confirmed');
        $seen = $this->item('received');

        $page = Livewire::test(Deliveries::class)
            ->assertSee($confirmed->order->number)
            ->assertSee('tel:555123456', false)
            ->assertDontSee($seen->order->number);

        $page->set('items.i'.$confirmed->id.'.serial', ' SN-998877 ')
            ->set('items.i'.$confirmed->id.'.months', 24)
            ->set('photos.i'.$confirmed->id, UploadedFile::fake()->image('serial.jpg'))
            ->call('saveItem', $confirmed->id)
            ->assertHasNoErrors()
            ->assertNotified(__('courier.deliveries.saved'));

        $confirmed->refresh();
        $this->assertSame('SN-998877', $confirmed->serial_number);
        $this->assertSame(24, $confirmed->warranty_months);
        $this->assertNotNull($confirmed->serial_photo);
        Storage::disk('local')->assertExists($confirmed->serial_photo);
        $this->assertTrue($confirmed->order->events()->where('note', 'like', '%გიორგი%SN-998877%')->exists());

        $page->set('items.i'.$seen->id, ['serial' => 'X', 'months' => 12])
            ->call('saveItem', $seen->id)
            ->assertForbidden();

        Livewire::test(Deliveries::class)
            ->set('items.i'.$confirmed->id.'.months', 0)
            ->call('saveItem', $confirmed->id)
            ->assertHasErrors('items.i'.$confirmed->id.'.months');
    }

    public function test_courier_marks_an_order_delivered_and_records_the_collected_money(): void
    {
        $this->actingAs(User::factory()->courier()->create(['name' => 'გიორგი']));
        Filament::setCurrentPanel(Filament::getPanel('courier'));

        $cash = $this->item('confirmed')->order;
        $onAccount = $this->item('confirmed')->order;
        $seen = $this->item('received')->order;

        Livewire::test(Deliveries::class)
            ->assertSee(__('courier.deliveries.mark_delivered'))
            ->set('collected.o'.$cash->id, true)
            ->call('markDelivered', $cash->id)
            ->assertNotified(__('courier.deliveries.delivered', ['number' => $cash->number]))
            ->assertDontSee($cash->number)
            ->call('markDelivered', $onAccount->id);

        $cash->refresh();
        $this->assertSame('completed', $cash->status);
        $this->assertNotNull($cash->completed_at);
        $this->assertSame(Order::PAYMENT_PAID, $cash->payment_status);
        $this->assertTrue($cash->events()->where('type', 'status')->where('to', 'completed')->where('note', 'like', '%გიორგი%')->exists());
        $this->assertTrue($cash->events()->where('type', 'payment')->where('note', 'like', '%გიორგი%')->exists());

        $onAccount->refresh();
        $this->assertSame('completed', $onAccount->status);
        $this->assertSame(Order::PAYMENT_PENDING, $onAccount->payment_status);

        Livewire::test(Deliveries::class)->call('markDelivered', $seen->id)->assertForbidden();
        $this->assertSame('received', $seen->fresh()->status);
    }

    public function test_serial_photos_are_only_shown_to_staff(): void
    {
        Storage::fake('local');
        $item = $this->item('confirmed');
        Storage::disk('local')->put('serial-photos/1/photo.jpg', 'jpeg-bytes');
        $item->update(['serial_photo' => 'serial-photos/1/photo.jpg']);

        $this->get(route('serial-photo', $item))->assertRedirect();
        $this->actingAs(User::factory()->create())->get(route('serial-photo', $item))->assertForbidden();
        $this->actingAs(User::factory()->courier()->create())->get(route('serial-photo', $item))->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get(route('serial-photo', $item))->assertOk();
    }
}
