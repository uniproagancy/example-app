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
use App\Services\Payments\Drivers\Flitt;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
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
            'services.flitt.merchant_id' => '1549901',
            'services.flitt.secret_key' => 'test',
            'bog.installment.public_key' => 'ipub',
            'bog.installment.secret_key' => 'isec',
            'bog.sdk_client_id' => '12345',
        ]);
    }

    protected function fakeBog(string $receiptStatus = 'approved'): void
    {
        Http::fake([
            'oauth2.bog.ge/*' => Http::response(['access_token' => 'token', 'expires_in' => 300]),
            'pay.flitt.com/api/checkout/url' => Http::response(['response' => [
                'response_status' => 'success',
                'checkout_url' => 'https://pay.flitt.com/merchants/test/index.html?token=abc',
                'payment_id' => '805230052',
            ]]),
            'pay.flitt.com/api/status/order_id' => fn (Request $request) => Http::response(['response' => $this->flittStatus(
                $request['request']['order_id'],
                $receiptStatus,
                (int) round((float) PaymentTransaction::query()->where('external_id', $request['request']['order_id'])->value('amount') * 100),
            )]),
            'installment.bog.ge/*' => Http::response([
                'status' => 'CREATED',
                'order_id' => 'inst-1',
                'links' => [['rel' => 'approve', 'href' => 'https://installment.bog.ge/approve/inst-1']],
            ]),
        ]);
    }

    protected function flittStatus(string $orderId, string $status, int $amount): array
    {
        $data = [
            'order_id' => $orderId, 'merchant_id' => 1549901, 'order_status' => $status, 'response_status' => 'success',
            'amount' => (string) $amount, 'currency' => 'GEL', 'masked_card' => '444455XXXXXX1111', 'tran_type' => 'purchase',
        ];

        return $data + ['signature' => app(Flitt::class)->signature($data)];
    }

    protected function order(string $method = 'card', array $attributes = []): Order
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

        $this->assertSame(['card', 'bog_installment', 'bog_split', 'cash', 'transfer'], PaymentMethod::forCheckout(1000)->pluck('code')->all());
        $this->assertSame(['card', 'cash', 'transfer'], PaymentMethod::forCheckout(50)->pluck('code')->all());
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
            ->call('choose', 'payment', 'card')
            ->call('placeOrder')
            ->assertHasNoErrors()
            ->assertRedirect('https://pay.flitt.com/merchants/test/index.html?token=abc');

        $order = Order::query()->sole();
        $transaction = $order->transactions()->sole();

        $this->assertSame('processing', $order->payment_status);
        $this->assertSame('flitt', $transaction->driver);
        $this->assertSame('pending', $transaction->status);
        $this->assertStringStartsWith($order->number.'-', $transaction->external_id);
        $this->assertSame((int) round((float) $order->total * 100), $transaction->request['amount']);
        $this->assertSame('GEL', $transaction->request['currency']);
        $this->assertArrayNotHasKey('signature', $transaction->request);
        $this->assertContains($order->id, session('placed_orders'));
        Http::assertSent(function ($request) {
            $sent = $request['request'] ?? [];

            return $request->url() === 'https://pay.flitt.com/api/checkout/url'
                && $sent['merchant_id'] === 1549901
                && $sent['signature'] === app(Flitt::class)->signature($sent)
                && str_contains($sent['server_callback_url'], '/payment/callback/flitt')
                && str_contains($sent['response_url'], 'signature=');
        });
    }

    public function test_bank_callback_settles_the_order_only_after_asking_the_bank(): void
    {
        $this->configureBog();
        $order = $this->order();
        $transaction = PaymentTransaction::query()->create([
            'order_id' => $order->id, 'driver' => 'flitt', 'external_id' => 'EL-flitt-1', 'amount' => 1000, 'status' => 'pending',
        ]);
        $this->fakeBog('approved');

        $forged = ['order_id' => 'EL-flitt-1', 'order_status' => 'approved', 'amount' => '100000', 'signature' => 'nope'];
        $this->postJson('/payment/callback/flitt', $forged)->assertOk();
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame('unmatched', PaymentCallback::query()->latest('id')->value('outcome'));

        $this->postJson('/payment/callback/flitt', $this->flittStatus('EL-flitt-1', 'approved', 100000))
            ->assertOk();

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('success', $transaction->fresh()->status);
        $this->assertSame(['type' => 'payment', 'from' => 'pending', 'to' => 'paid'], $order->events()->first()->only(['type', 'from', 'to']));

        $record = PaymentCallback::query()->latest('id')->first();
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
            'order_id' => $order->id, 'driver' => 'flitt', 'external_id' => 'EL-flitt-1', 'amount' => 1000, 'status' => 'pending',
        ]);
        $this->fakeBog('declined');

        $this->get(URL::temporarySignedRoute('payment.return', now()->addHour(), ['number' => $order->number, 'status' => 'fail']))
            ->assertRedirect($order->pageUrl());

        $this->assertSame('failed', $order->fresh()->payment_status);

        $this->get($order->pageUrl())
            ->assertOk()
            ->assertSee(__('payments.order.return_failed'))
            ->assertSee(__('payments.statuses.failed'));

        Livewire::test('pages::order', ['number' => $order->number])
            ->call('pay')
            ->assertRedirect('https://pay.flitt.com/merchants/test/index.html?token=abc');

        $this->assertSame(['pending', 'failed'], $order->transactions()->orderByDesc('id')->pluck('status')->all());
    }

    public function test_flitt_signs_like_its_documentation_and_rejects_wrong_amounts(): void
    {
        $this->configureBog();
        $flitt = app(Flitt::class);

        $this->assertSame(sha1('test|1000|GEL|1549901|Test payment|TestOrder2|http://myshop/callback/'), $flitt->signature([
            'server_callback_url' => 'http://myshop/callback/', 'order_id' => 'TestOrder2', 'currency' => 'GEL',
            'merchant_id' => 1549901, 'order_desc' => 'Test payment', 'amount' => 1000,
        ]));

        $order = $this->order(attributes: ['payment_status' => 'processing']);
        $transaction = PaymentTransaction::query()->create([
            'order_id' => $order->id, 'driver' => 'flitt', 'external_id' => 'EL-flitt-2', 'amount' => 1000, 'status' => 'pending',
        ]);
        Http::fake(['pay.flitt.com/api/status/order_id' => Http::response(['response' => $this->flittStatus('EL-flitt-2', 'approved', 100)])]);

        $this->assertFalse($flitt->confirm($transaction));
        $this->assertFalse($order->fresh()->isPaid());
    }

    public function test_the_bank_can_post_the_buyer_back_to_the_shop(): void
    {
        $this->configureBog();
        $order = $this->order(attributes: ['payment_status' => 'processing']);
        PaymentTransaction::query()->create([
            'order_id' => $order->id, 'driver' => 'flitt', 'external_id' => 'EL-flitt-3', 'amount' => 1000, 'status' => 'pending',
        ]);
        $this->fakeBog('approved');

        $this->post(URL::temporarySignedRoute('payment.return', now()->addHour(), ['number' => $order->number]), ['order_status' => 'approved'])
            ->assertRedirect($order->pageUrl());

        $this->assertTrue($order->fresh()->isPaid());
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
            'order_id' => $order->id, 'driver' => 'flitt', 'external_id' => 'EL-flitt-1', 'amount' => 1000, 'status' => 'pending',
        ]);
        $this->fakeBog('approved');

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
            'order_id' => $order->id, 'driver' => 'flitt', 'external_id' => 'EL-flitt-1', 'amount' => 1000, 'status' => 'pending',
        ]);
        $this->fakeBog('processing');

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
            ->callTableAction('delete', PaymentMethod::query()->where('code', 'card')->sole())
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

        $this->assertModelExists(PaymentMethod::query()->where('code', 'card')->sole());
        $liberty = PaymentMethod::query()->where('code', 'liberty_installment')->sole();
        $this->assertSame('ლიბერთი განვადება', $liberty->name);
        $this->assertFalse($liberty->is_online);
        $this->assertFalse($liberty->on_quick_order);
    }
}
