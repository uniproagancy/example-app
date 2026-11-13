<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Payments\Drivers\Flitt;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class FlittRefundTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected Order $order;

    protected PaymentTransaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.flitt.merchant_id' => '1549901', 'services.flitt.secret_key' => 'test']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->order = Order::query()->create([
            'number' => 'EL-777001', 'status' => 'received', 'first_name' => 'Nino', 'last_name' => 'Beridze', 'phone' => '555000000',
            'email' => 'nino@example.com', 'shipping_method' => 'courier', 'city' => 'tbilisi', 'address' => 'Rustaveli 1',
            'payment_method' => 'card', 'payment_status' => Order::PAYMENT_PAID, 'paid_at' => now(),
            'subtotal' => 100, 'discount' => 0, 'shipping' => 0, 'total' => 100, 'locale' => 'ka',
        ]);
        $this->transaction = PaymentTransaction::query()->create([
            'order_id' => $this->order->id, 'driver' => 'flitt', 'external_id' => 'EL-777001-abc', 'amount' => 100,
            'status' => PaymentTransaction::SUCCESS, 'paid_at' => now(),
        ]);
    }

    protected function fakeReverse(string $status = 'approved'): void
    {
        Http::fake(['pay.flitt.com/api/reverse/order_id' => function (Request $request) use ($status) {
            $sent = $request['request'];
            $data = [
                'order_id' => $sent['order_id'], 'merchant_id' => 1549901, 'reverse_status' => $status, 'response_status' => 'success',
                'reverse_id' => $sent['reverse_id'], 'reversal_amount' => (string) $sent['amount'], 'transaction_id' => '2011273408',
            ];

            return Http::response(['response' => $data + ['signature' => app(Flitt::class)->signature($data)]]);
        }]);
    }

    public function test_an_admin_refunds_part_then_the_rest_of_a_card_payment(): void
    {
        $this->actingAs(User::factory()->admin()->create(['email' => 'boss@elio.test']));
        $this->fakeReverse();

        Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])
            ->assertActionVisible('refund')
            ->callAction('refund', ['amount' => 30, 'comment' => 'ერთი ნივთი დააბრუნა'])
            ->assertHasNoActionErrors()
            ->assertNotified(__('payments.refund.done', ['amount' => '30 ₾']));

        Http::assertSent(function (Request $request) {
            $sent = $request['request'];

            return $request->url() === 'https://pay.flitt.com/api/reverse/order_id'
                && $sent['order_id'] === 'EL-777001-abc'
                && $sent['amount'] === 3000
                && $sent['currency'] === 'GEL'
                && $sent['email'] === 'boss@elio.test'
                && $sent['signature'] === app(Flitt::class)->signature($sent);
        });

        $this->assertSame(Order::PAYMENT_PAID, $this->order->fresh()->payment_status);
        $this->assertEquals(70, app(Flitt::class)->refundable($this->transaction->fresh()));

        Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])
            ->callAction('refund', ['amount' => 80])
            ->assertHasActionErrors(['amount']);

        Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])
            ->callAction('refund', ['amount' => 70])
            ->assertHasNoActionErrors();

        $this->assertSame(Order::PAYMENT_REFUNDED, $this->order->fresh()->payment_status);
        $this->assertEquals(0, app(Flitt::class)->refundable($this->transaction->fresh()));

        Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])
            ->assertActionHidden('refund');
    }

    public function test_a_refused_or_unsigned_refund_changes_nothing(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Http::fake(['pay.flitt.com/api/reverse/order_id' => Http::response(['response' => [
            'response_status' => 'success', 'reverse_status' => 'approved', 'order_id' => 'EL-777001-abc', 'signature' => 'forged',
        ]])]);

        Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])
            ->callAction('refund', ['amount' => 100])
            ->assertNotified(__('payments.refund.failed'));

        $this->assertSame(Order::PAYMENT_PAID, $this->order->fresh()->payment_status);
        $this->assertEquals(100, app(Flitt::class)->refundable($this->transaction->fresh()));
    }

    public function test_operators_and_unpaid_orders_have_no_refund(): void
    {
        $this->actingAs(User::factory()->operator()->create());

        Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])
            ->assertActionHidden('refund');

        $this->actingAs(User::factory()->admin()->create());
        $this->transaction->update(['status' => PaymentTransaction::PENDING]);

        Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])
            ->assertActionHidden('refund');
    }
}
