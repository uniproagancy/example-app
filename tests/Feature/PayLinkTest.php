<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PayLinkTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.flitt.merchant_id' => '1549901',
            'services.flitt.secret_key' => 'test',
            'bog.installment.public_key' => 'ipub',
            'bog.installment.secret_key' => 'isec',
            'bog.sdk_client_id' => '12345',
        ]);
    }

    protected function order(array $attributes = []): Order
    {
        $order = Order::query()->create([
            'number' => 'EL-'.random_int(100000, 999999), 'status' => 'received', 'first_name' => 'ნინო', 'last_name' => 'ბერიძე', 'phone' => '555 12 34 56',
            'shipping_method' => 'courier', 'city' => 'tbilisi', 'address' => 'რუსთაველი 1',
            'payment_method' => 'cash', 'payment_status' => Order::PAYMENT_PENDING,
            'subtotal' => 900, 'discount' => 0, 'shipping' => 0, 'total' => 900, 'locale' => 'ka',
            ...$attributes,
        ]);
        $order->items()->create(['name' => 'JBL Charge 6', 'unit_price' => 450, 'quantity' => 2, 'total' => 900]);

        return $order;
    }

    protected function viewOrder(Order $order)
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()]);
    }

    public function test_admin_generates_an_installment_link_that_switches_the_order_and_starts_paying(): void
    {
        $order = $this->order();

        $page = $this->viewOrder($order)
            ->assertSee(__('admin.orders.pay_link_generate'))
            ->assertSee('საქართველოს ბანკის ონლაინ განვადება')
            ->assertDontSee('საბანკო გადარიცხვა</option>', false)
            ->set('linkMethod', 'bog_installment')
            ->set('linkDays', 3)
            ->call('generateLink')
            ->assertHasNoErrors();

        $link = $page->get('generatedLink');
        $this->assertStringContainsString('/payment/link/'.$order->number, $link);
        $this->assertStringContainsString('method=bog_installment', $link);
        $this->assertTrue($order->events()->where('note', 'like', '%3 დღე%')->exists());

        auth()->logout();

        $this->get($link)->assertRedirect($order->pageUrl().'?pay=1');

        $order->refresh();
        $this->assertSame('bog_installment', $order->payment_method);
        $this->assertTrue($order->mayBePaidByVisitor());
        $this->assertTrue($order->events()->where('note', 'like', '%ადგილზე გადახდა → საქართველოს ბანკის ონლაინ განვადება%')->exists());
    }

    public function test_link_cannot_be_tampered_with_or_used_after_payment(): void
    {
        $order = $this->order();
        $link = $order->payLink(7, 'card');

        $this->get(str_replace('method=card', 'method=bog_installment', $link))->assertForbidden();

        $order->update(['payment_status' => Order::PAYMENT_PAID]);
        $this->get($link)->assertRedirect($order->pageUrl());
        $this->assertSame('cash', $order->refresh()->payment_method);
    }

    public function test_methods_that_do_not_fit_the_total_are_rejected(): void
    {
        $order = $this->order(['subtotal' => 50, 'total' => 50]);

        $this->viewOrder($order)
            ->assertDontSee('საქართველოს ბანკის ონლაინ განვადება</option>', false)
            ->set('linkMethod', 'bog_installment')
            ->call('generateLink')
            ->assertHasErrors('linkMethod');

        $this->get($order->payLink(7, 'bog_installment'))->assertRedirect($order->pageUrl());
        $this->assertSame('cash', $order->refresh()->payment_method);
    }

    public function test_admin_sends_the_link_by_sms(): void
    {
        Http::fake(['smsoffice.ge/*' => Http::response(['Success' => true, 'Message' => 'OK', 'Output' => null, 'ErrorCode' => 0])]);
        config(['services.smsoffice.key' => 'sms-key', 'services.smsoffice.sender' => 'ELIVO.GE']);
        $order = $this->order();

        $this->viewOrder($order)
            ->set('linkMethod', 'card')
            ->call('generateLink')
            ->call('sendLinkSms')
            ->assertNotified(__('admin.orders.pay_link_sms_sent', ['phone' => $order->phone]));

        Http::assertSent(fn (Request $request) => str_contains((string) $request['content'], '/payment/link/'.$order->number)
            && str_contains((string) $request['content'], 'method=card'));
    }
}
