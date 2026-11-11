<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\PaymentMethods\Pages\ManagePaymentMethods;
use App\Models\Order;
use App\Models\PaymentCallback;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function configureBog(): void
    {
        config([
            'bog.payment.public_key' => 'pub',
            'bog.payment.secret_key' => 'sec',
            'bog.installment.public_key' => 'ipub',
            'bog.installment.secret_key' => 'isec',
            'bog.sdk_client_id' => '12345',
        ]);
    }

    protected function fakeBog(string $receiptStatus = 'completed'): void
    {
        Http::fake([
            'oauth2.bog.ge/*' => Http::response(['access_token' => 'token', 'expires_in' => 300]),
            'api.bog.ge/payments/v1/ecommerce/orders' => Http::response([
                'id' => 'bog-order-1',
                '_links' => ['redirect' => ['href' => 'https://payment.bog.ge/?order_id=bog-order-1']],
            ]),
            'api.bog.ge/payments/v1/receipt/*' => Http::response([
                'order_status' => ['key' => $receiptStatus],
                'external_order_id' => Order::query()->latest('id')->value('number'),
            ]),
            'installment.bog.ge/*' => Http::response([
                'status' => 'CREATED',
                'order_id' => 'inst-1',
                'links' => [['rel' => 'approve', 'href' => 'https://installment.bog.ge/approve/inst-1']],
            ]),
        ]);
    }

    protected function order(string $method = 'bog_card', array $attributes = []): Order
    {
        $iphone = Product::query()->where('sku', 'ip16')->sole();

        $order = Order::query()->create([
            'number' => 'EL-'.random_int(100000, 999999), 'status' => 'new', 'source' => 'checkout', 'first_name' => 'Nino',
            'last_name' => 'Beridze', 'phone' => '555000000', 'email' => 'nino@example.com', 'shipping_method' => 'courier',
            'city' => 'tbilisi', 'address' => 'Rustaveli 1', 'payment_method' => $method, 'payment_status' => 'pending',
            'subtotal' => 1000, 'discount' => 0, 'shipping' => 0, 'total' => 1000, 'locale' => 'ka', ...$attributes,
        ]);

        $order->items()->create(['product_id' => $iphone->id, 'name' => 'iPhone 16 Pro', 'unit_price' => 1000, 'quantity' => 1, 'total' => 1000]);

        return $order;
    }

    public function test_online_methods_reach_checkout_only_once_their_bank_keys_are_set(): void
    {
        $this->assertSame(['cash', 'transfer'], PaymentMethod::forCheckout(1000)->pluck('code')->all());

        $this->configureBog();

        $this->assertSame(['bog_card', 'bog_installment', 'bog_split', 'cash', 'transfer'], PaymentMethod::forCheckout(1000)->pluck('code')->all());
        $this->assertSame(['bog_card', 'cash', 'transfer'], PaymentMethod::forCheckout(50)->pluck('code')->all());
    }

    public function test_checkout_with_a_card_sends_the_buyer_to_the_bank(): void
    {
        $this->configureBog();
        $this->fakeBog();

        $speaker = Product::query()->where('sku', 'jbl')->sole();
        session(['cart' => [(string) $speaker->id => 1]]);

        Livewire::test('pages::checkout')
            ->set('firstName', 'ნინო')
            ->set('lastName', 'ბერიძე')
            ->set('phone', '+995 555 12 34 56')
            ->set('email', 'nino@mail.ge')
            ->set('address', 'ჭავჭავაძის 21')
            ->call('choose', 'payment', 'bog_card')
            ->call('placeOrder')
            ->assertHasNoErrors()
            ->assertRedirect('https://payment.bog.ge/?order_id=bog-order-1');

        $order = Order::query()->sole();
        $transaction = $order->transactions()->sole();

        $this->assertSame('processing', $order->payment_status);
        $this->assertSame('bog-card', $transaction->driver);
        $this->assertSame('pending', $transaction->status);
        $this->assertSame((string) $order->number, $transaction->request['external_order_id']);
        $this->assertEquals((float) $order->total, $transaction->request['purchase_units']['total_amount']);
        $this->assertContains($order->id, session('placed_orders'));
        Http::assertSent(fn ($request) => $request->url() === 'https://api.bog.ge/payments/v1/ecommerce/orders' && $request->hasHeader('Authorization', 'Bearer token'));
    }

    public function test_bank_callback_settles_the_order_only_after_asking_the_bank(): void
    {
        $this->configureBog();
        $order = $this->order();
        $transaction = PaymentTransaction::query()->create([
            'order_id' => $order->id, 'driver' => 'bog-card', 'external_id' => 'bog-order-1', 'amount' => 1000, 'status' => 'pending',
        ]);
        $this->fakeBog('completed');

        $this->postJson('/payment/callback/bog-card', ['body' => ['order_id' => 'bog-order-1', 'order_status' => ['key' => 'completed']]])
            ->assertOk();

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('success', $transaction->fresh()->status);
        $this->assertSame(['type' => 'payment', 'from' => 'pending', 'to' => 'paid'], $order->events()->first()->only(['type', 'from', 'to']));

        $record = PaymentCallback::query()->sole();
        $this->assertSame('paid', $record->outcome);
        $this->assertTrue($record->confirmed);

        $this->postJson('/payment/callback/nobody', ['x' => 1])->assertOk();
        $this->assertSame('unknown_driver', PaymentCallback::query()->latest('id')->value('outcome'));
    }

    public function test_a_refused_payment_marks_the_order_failed_and_the_buyer_can_retry(): void
    {
        $this->refreshApplicationWithLocale('ka');
        $this->configureBog();
        $order = $this->order(attributes: ['payment_status' => 'processing']);
        PaymentTransaction::query()->create([
            'order_id' => $order->id, 'driver' => 'bog-card', 'external_id' => 'bog-order-1', 'amount' => 1000, 'status' => 'pending',
        ]);
        $this->fakeBog('rejected');

        $this->get(URL::temporarySignedRoute('payment.return', now()->addHour(), ['number' => $order->number, 'status' => 'fail']))
            ->assertRedirect($order->pageUrl());

        $this->assertSame('failed', $order->fresh()->payment_status);

        $this->get($order->pageUrl())
            ->assertOk()
            ->assertSee(__('payments.order.return_failed'))
            ->assertSee(__('payments.statuses.failed'));

        Livewire::test('pages::order', ['number' => $order->number])
            ->call('pay')
            ->assertRedirect('https://payment.bog.ge/?order_id=bog-order-1');

        $this->assertSame(['pending', 'failed'], $order->transactions()->orderByDesc('id')->pluck('status')->all());
    }

    public function test_order_page_is_private_and_opens_through_a_signed_pay_link(): void
    {
        $this->refreshApplicationWithLocale('ka');
        $order = $this->order('transfer');

        $this->get($order->pageUrl())->assertNotFound();
        $this->get('/payment/link/'.$order->number)->assertForbidden();

        $this->get($order->payLink())->assertRedirect($order->pageUrl());
        $this->get($order->pageUrl())
            ->assertOk()
            ->assertSee($order->number)
            ->assertSee(__('payments.order.offline'))
            ->assertDontSee('wire:click="pay"', false);
    }

    public function test_bog_installment_opens_the_calculator_and_creates_the_application(): void
    {
        $this->configureBog();
        $this->fakeBog();
        $order = $this->order('bog_installment');
        $order->rememberInSession();

        Livewire::withQueryParams(['pay' => 1])
            ->test('pages::order', ['number' => $order->number])
            ->assertSet('autoPay', true)
            ->call('pay')
            ->assertDispatched('bog:installment', fn (string $name, array $params) => (float) $params['amount'] === 1050.0 && $params['bnpl'] === false && str_contains($params['url'], 'signature='));

        $this->postJson(URL::temporarySignedRoute('payment.bog.installment', now()->addHour(), ['number' => $order->number]), ['month' => 12])
            ->assertOk()
            ->assertJson(['orderId' => 'inst-1', 'redirectUrl' => 'https://installment.bog.ge/approve/inst-1']);

        $transaction = $order->transactions()->sole();
        $this->assertSame(12, $transaction->months);
        $this->assertSame('1050.00', $transaction->amount);
        $this->assertSame('STANDARD', $transaction->request['installment_type']);

        $this->postJson('/payment/bog/installment/'.$order->number, ['month' => 12])->assertOk();
        session()->flush();
        $this->postJson('/payment/bog/installment/'.$order->number, ['month' => 12])->assertForbidden();
    }

    public function test_credo_hands_the_basket_to_its_widget(): void
    {
        config(['credo.merchant_id' => 'shop-7', 'credo.secret' => 'secret']);
        $order = $this->order('credo_installment');
        $order->rememberInSession();

        Livewire::test('pages::order', ['number' => $order->number])->call('pay');

        $transaction = $order->transactions()->sole();
        $this->assertSame('credo-installment', $transaction->driver);
        $this->assertSame('1100.00', $transaction->amount);

        $this->get($transaction->redirect_url)
            ->assertOk()
            ->assertSee('ganvadeba.credo.ge', false)
            ->assertSee('credoinstallment', false)
            ->assertSee('shop-7', false);
    }

    public function test_sweeper_settles_pending_payments(): void
    {
        $this->configureBog();
        $order = $this->order(attributes: ['payment_status' => 'processing']);
        PaymentTransaction::query()->create([
            'order_id' => $order->id, 'driver' => 'bog-card', 'external_id' => 'bog-order-1', 'amount' => 1000, 'status' => 'pending',
        ]);
        $this->fakeBog('completed');

        $this->artisan('payments:check')->expectsOutputToContain('1 checked, 1 confirmed.')->assertSuccessful();

        $this->assertTrue($order->fresh()->isPaid());
    }

    public function test_admin_sets_payment_status_asks_the_bank_and_manages_methods(): void
    {
        $this->configureBog();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $order = $this->order(attributes: ['payment_status' => 'processing']);
        PaymentTransaction::query()->create([
            'order_id' => $order->id, 'driver' => 'bog-card', 'external_id' => 'bog-order-1', 'amount' => 1000, 'status' => 'pending',
        ]);
        $this->fakeBog('in_progress');

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertSee(__('admin.orders.ask_bank'))
            ->assertSee(__('admin.orders.pay_link'))
            ->call('askBank')
            ->assertNotified(__('payments.check.not_yet'))
            ->set('paymentStatus', 'paid')
            ->call('changePaymentStatus')
            ->assertNotified(__('admin.orders.payment_status_changed'));

        $order->refresh();
        $this->assertTrue($order->isPaid());
        $this->assertSame(['payment', 'processing', 'paid', $admin->id], array_values($order->events()->first()->only(['type', 'from', 'to', 'user_id'])));

        $cash = PaymentMethod::query()->where('code', 'cash')->sole();

        Livewire::test(ManagePaymentMethods::class)
            ->assertCanSeeTableRecords([$cash])
            ->callTableAction('delete', PaymentMethod::query()->where('code', 'bog_card')->sole())
            ->assertNotified(__('admin.payment_methods.cannot_delete'));

        Livewire::test(ManagePaymentMethods::class)
            ->callAction('create', [
                'code' => 'liberty_installment',
                'kaTranslation' => ['name' => 'ლიბერთი განვადება'],
                'is_active' => true,
                'on_quick_order' => false,
                'min_total' => 200,
            ])
            ->assertHasNoActionErrors();

        $this->assertModelExists(PaymentMethod::query()->where('code', 'bog_card')->sole());
        $liberty = PaymentMethod::query()->where('code', 'liberty_installment')->sole();
        $this->assertSame('ლიბერთი განვადება', $liberty->name);
        $this->assertFalse($liberty->is_online);
        $this->assertFalse($liberty->on_quick_order);
    }
}
