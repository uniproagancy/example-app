<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\User;
use App\Support\Cart;
use App\Support\Catalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function fillCart(): array
    {
        $iphone = Product::query()->where('sku', 'ip16')->with('options')->first();
        $storage512 = $iphone->options->first(fn ($option) => $option->label['en'] === '512 GB')->id;
        $desert = $iphone->options->firstWhere('is_default', true)->id;
        $speaker = Product::query()->where('sku', 'jbl')->first();

        $iphoneKey = $iphone->id.':'.min($desert, $storage512).','.max($desert, $storage512);
        session(['cart' => [$iphoneKey => 1, (string) $speaker->id => 2]]);

        return [$iphone, $speaker, $iphoneKey];
    }

    protected function filled(): Testable
    {
        return Livewire::test('pages::checkout')
            ->set('firstName', 'ნინო')
            ->set('lastName', 'ბერიძე')
            ->set('phone', '+995 555 12 34 56')
            ->set('email', 'Nino@Mail.ge')
            ->set('address', 'ჭავჭავაძის გამზ. 21');
    }

    public function test_checkout_page_renders_cart_lines(): void
    {
        $this->refreshApplicationWithLocale('ka');
        $this->fillCart();

        $this->get('/ka/checkout')
            ->assertOk()
            ->assertSee('<title>შეკვეთის გაფორმება — ELIO.GE</title>', false)
            ->assertSee('iPhone 16 Pro')
            ->assertSee('Desert Titanium · 512 GB')
            ->assertSee('4 599 ₾')
            ->assertSee('საკონტაქტო ინფორმაცია');
    }

    public function test_order_is_placed_with_items_and_cart_is_cleared(): void
    {
        [$iphone, $speaker] = $this->fillCart();
        $speakerPrice = (float) $speaker->price;

        $this->filled()
            ->call('choose', 'payment', 'transfer')
            ->call('placeOrder')
            ->assertHasNoErrors()
            ->assertSee(__('checkout.done_title'));

        $order = Order::query()->with('items')->sole();

        $this->assertSame('nino@mail.ge', $order->email);
        $this->assertSame('transfer', $order->payment_method);
        $this->assertSame('pending', $order->payment_status);
        $this->assertNull($order->bank);
        $this->assertSame('0.00', $order->shipping);
        $this->assertEquals(4599 + $speakerPrice * 2, (float) $order->total);
        $this->assertCount(2, $order->items);
        $this->assertSame(['Desert Titanium', '512 GB'], $order->items->firstWhere('product_id', $iphone->id)->options);
        $this->assertEmpty(session('cart', []));
    }

    public function test_promo_code_changes_the_total_and_express_is_not_offered(): void
    {
        $this->fillCart();

        $component = $this->filled()
            ->set('promoInput', 'nope')
            ->call('applyPromo')
            ->assertSet('promoCode', null)
            ->set('promoInput', 'elio10')
            ->call('applyPromo')
            ->assertSet('promoCode', 'ELIO10')
            ->call('choose', 'shipping', 'express')
            ->assertSet('shipping', 'courier')
            ->assertDontSee('3 საათში');

        $totals = $component->get('totals');

        $this->assertEquals(round($totals['subtotal'] * 0.1), $totals['discount']);
        $this->assertEquals(0, $totals['shipping']);

        $component->call('placeOrder')->assertHasNoErrors();

        $order = Order::query()->sole();
        $this->assertSame('ELIO10', $order->promo_code);
        $this->assertSame('courier', $order->shipping_method);
        $this->assertEquals($totals['total'], (float) $order->total);
    }

    public function test_delivery_is_free_only_over_the_threshold(): void
    {
        $this->assertEquals(Catalog::SHIPPING_PRICE, Cart::shippingFor(40));
        $this->assertEquals(0, Cart::shippingFor(Catalog::FREE_SHIPPING_FROM));
        $this->assertEquals(0, Cart::shippingFor(0));
    }

    public function test_delivery_shows_only_as_a_fee_when_one_applies(): void
    {
        $speaker = Product::query()->where('sku', 'jbl')->sole();
        $speaker->update(['price' => 40, 'old_price' => null]);
        Catalog::flush();
        session(['cart' => [(string) $speaker->id => 1]]);
        $fee = Catalog::money(Catalog::SHIPPING_PRICE);

        Livewire::test('pages::checkout')
            ->assertDontSee(__('checkout.methods.courier.text'))
            ->assertSet('totals.shipping', (float) Catalog::SHIPPING_PRICE)
            ->assertSee(__('checkout.shipping'))
            ->assertSee($fee);

        $this->fillCart();

        Livewire::test('pages::checkout')
            ->assertSet('totals.shipping', 0.0)
            ->assertDontSee(__('checkout.free'));
    }

    public function test_expired_promo_code_is_rejected(): void
    {
        $this->fillCart();
        PromoCode::query()->where('code', 'ELIO10')->update(['ends_at' => now()->subDay()]);

        $this->filled()
            ->set('promoInput', 'ELIO10')
            ->call('applyPromo')
            ->assertSet('promoCode', null)
            ->assertSet('promoMessage', __('checkout.promo_invalid'));
    }

    public function test_checkout_validates_fields_and_requires_items(): void
    {
        Livewire::test('pages::checkout')
            ->set('agree', false)
            ->set('city', 'paris')
            ->call('placeOrder')
            ->assertHasErrors(['firstName', 'lastName', 'phone', 'email', 'address', 'city', 'agree']);

        $this->filled()->call('placeOrder')->assertHasErrors('cart');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_quantity_changes_update_the_session_cart(): void
    {
        [, , $iphoneKey] = $this->fillCart();

        Livewire::test('pages::checkout')
            ->call('increment', $iphoneKey)
            ->assertDispatched('cart-refresh')
            ->call('decrement', $iphoneKey)
            ->call('decrement', $iphoneKey);

        $this->assertSame(1, session('cart')[$iphoneKey]);
    }

    public function test_signed_in_user_is_prefilled_and_sees_order_in_account(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->fillCart();

        $user = User::factory()->create(['name' => 'Giorgi Beridze', 'phone' => '599 00 00 00']);
        $user->addresses()->create(['title' => 'Home', 'line' => 'Rustaveli Ave 1', 'is_default' => true]);

        Livewire::actingAs($user)
            ->test('pages::checkout')
            ->assertSet('firstName', 'Giorgi')
            ->assertSet('lastName', 'Beridze')
            ->assertSet('address', 'Rustaveli Ave 1')
            ->call('placeOrder')
            ->assertHasNoErrors();

        $order = Order::query()->sole();
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame('new', $order->status);

        $this->actingAs($user)
            ->get('/en/account?tab=orders')
            ->assertOk()
            ->assertSee('#'.$order->number)
            ->assertSee('New order');
    }
}
