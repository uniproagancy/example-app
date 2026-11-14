<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\Orders\OrderNotifier;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class StatusSmsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutDefer();
        Http::fake(['smsoffice.ge/*' => Http::response(['Success' => true, 'Message' => 'OK', 'Output' => null, 'ErrorCode' => 0])]);
        config(['services.smsoffice.key' => 'sms-key', 'services.smsoffice.sender' => 'ELIVO.GE']);
    }

    protected function order(): Order
    {
        return Order::query()->create([
            'number' => 'EL-'.random_int(100000, 999999), 'status' => 'new', 'first_name' => 'ნინო', 'phone' => '555 12 34 56',
            'payment_method' => 'cash', 'payment_status' => Order::PAYMENT_PENDING,
            'subtotal' => 120, 'discount' => 0, 'shipping' => 0, 'total' => 120, 'locale' => 'ka',
        ]);
    }

    public function test_buyer_gets_an_sms_only_for_the_statuses_chosen_in_settings(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageSettings::class)
            ->fillForm(['status_sms' => ['shipped', 'completed']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['shipped', 'completed'], OrderNotifier::statusSmsList());

        $order = $this->order();

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->set('status', 'received')
            ->call('changeStatus');

        Http::assertNothingSent();

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->set('status', 'shipped')
            ->call('changeStatus');

        Http::assertSent(fn (Request $request) => str_contains((string) $request['content'], $order->number)
            && str_contains((string) $request['content'], 'კურიერს'));
        $this->assertTrue($order->events()->where('note', 'like', '%კურიერთან გადაეცა%')->exists());
    }

    public function test_nothing_is_sent_while_status_sms_is_off(): void
    {
        Setting::put('status_sms', null);
        $order = $this->order();

        $order->update(['status' => 'completed']);

        Http::assertNothingSent();
    }
}
