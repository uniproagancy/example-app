<?php

namespace Tests\Feature;

use App\Filament\Resources\AbandonedCarts\Pages\ManageAbandonedCarts;
use App\Models\AbandonedCart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Cart;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AbandonedCartTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function fillCart(): Product
    {
        $speaker = Product::query()->where('sku', 'jbl')->first();
        session(['cart' => [(string) $speaker->id => 2]]);

        return $speaker;
    }

    public function test_cart_is_remembered_once_the_buyer_leaves_a_phone_and_recovered_on_order(): void
    {
        $speaker = $this->fillCart();

        $checkout = Livewire::test('pages::checkout')
            ->set('firstName', 'ნინო')
            ->set('lastName', 'ბერიძე');

        $this->assertSame(0, AbandonedCart::query()->count());

        $checkout->set('phone', '555 12 34 56');

        $cart = AbandonedCart::query()->sole();
        $this->assertSame('555 12 34 56', $cart->phone);
        $this->assertSame('ნინო ბერიძე', $cart->name);
        $this->assertSame([(string) $speaker->id => 2], $cart->items);
        $this->assertSame(2, $cart->itemCount());
        $this->assertEquals((float) $speaker->price * 2, (float) $cart->subtotal);

        $checkout->set('email', 'nino@mail.ge')
            ->set('address', 'რუსთაველი 1')
            ->call('choose', 'payment', 'cash')
            ->call('placeOrder')
            ->assertHasNoErrors();

        $this->assertSame(1, AbandonedCart::query()->count());
        $this->assertSame(Order::query()->sole()->id, $cart->fresh()->order_id);
        $this->assertSame(0, AbandonedCart::query()->abandoned()->count());
    }

    public function test_restore_link_refills_the_cart_and_opens_checkout(): void
    {
        $speaker = Product::query()->where('sku', 'jbl')->first();
        $cart = AbandonedCart::factory()->create(['items' => [(string) $speaker->id => 3]]);

        $this->get('/cart/restore/'.$cart->token)->assertForbidden();

        $this->get($cart->restoreUrl())->assertRedirect(url('ka/checkout'));
        $this->assertSame([(string) $speaker->id => 3], Cart::items());
        $this->assertSame($cart->token, session(AbandonedCart::SESSION_KEY));
    }

    public function test_admin_sees_open_carts_and_sends_a_reminder(): void
    {
        Http::fake(['smsoffice.ge/*' => Http::response(['Success' => true, 'Message' => 'OK', 'Output' => null, 'ErrorCode' => 0])]);
        config(['services.smsoffice.key' => 'sms-key', 'services.smsoffice.sender' => 'ELIVO.GE']);

        $open = AbandonedCart::factory()->create(['name' => 'ლევან გელაშვილი']);
        $fresh = AbandonedCart::factory()->create(['name' => 'ახლა ავსებს', 'updated_at' => now()]);
        $recovered = AbandonedCart::factory()->create(['name' => 'უკვე იყიდა']);
        $recovered->forceFill(['order_id' => Order::query()->create([
            'number' => 'EL-111111', 'status' => 'new', 'first_name' => 'ა', 'phone' => '555000000', 'payment_method' => 'cash',
            'payment_status' => 'pending', 'subtotal' => 1, 'discount' => 0, 'shipping' => 0, 'total' => 1, 'locale' => 'ka',
        ])->id])->save();

        $this->actingAs(User::factory()->operator()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageAbandonedCarts::class)
            ->assertCanSeeTableRecords([$open])
            ->assertCanNotSeeTableRecords([$fresh, $recovered])
            ->callTableAction('remind', $open)
            ->assertNotified(__('admin.orders.pay_link_sms_sent', ['phone' => $open->phone]));

        Http::assertSent(fn (Request $request) => str_contains((string) $request['content'], '/cart/restore/'.$open->token));
        $this->assertNotNull($open->fresh()->contacted_at);
        $this->assertNotNull($open->fresh()->reminded_at);
    }
}
