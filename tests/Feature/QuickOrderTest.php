<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuickOrderTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function iphone(): Product
    {
        return Product::query()->where('sku', 'ip16')->with('options')->first();
    }

    public function test_cart_keeps_option_combinations_as_separate_lines_with_their_prices(): void
    {
        $iphone = $this->iphone();
        $black = $iphone->options->firstWhere('hex', '#3C3C3F')->id;
        $storage512 = $iphone->options->first(fn ($option) => $option->label['en'] === '512 GB')->id;

        $component = Livewire::test('cart')
            ->dispatch('cart-add', id: $iphone->id, qty: 2, options: [$black, $storage512])
            ->dispatch('cart-add', id: $iphone->id);

        $items = $component->get('items');
        $this->assertCount(2, $items);
        $this->assertSame(2, $items[$iphone->id.':'.min($black, $storage512).','.max($black, $storage512)]);

        $component->assertSee('9 198 ₾')
            ->assertSee('Black Titanium · 512 GB')
            ->assertSee('3 899 ₾');
    }

    public function test_cart_rejects_option_ids_from_other_products(): void
    {
        $iphone = $this->iphone();
        $other = Product::query()->where('sku', 'jbl')->first();

        $component = Livewire::test('cart')
            ->dispatch('cart-add', id: $other->id, options: [$iphone->options->first()->id]);

        $this->assertSame([(string) $other->id], array_map('strval', array_keys($component->get('items'))));
    }

    public function test_quick_order_is_saved_with_selected_options(): void
    {
        $iphone = $this->iphone();
        $storage1tb = $iphone->options->first(fn ($option) => $option->label['en'] === '1 TB')->id;

        Livewire::test('quick-order', ['productId' => $iphone->id])
            ->set('name', 'ნინო ბერიძე')
            ->set('phone', '555 12 34 56')
            ->set('payment', 'cash')
            ->call('submit', 2, [$storage1tb])
            ->assertHasNoErrors()
            ->assertSet('sent', true);

        $order = Order::query()->with('items')->sole();
        $this->assertSame('quick', $order->source);
        $this->assertSame('new', $order->status);
        $this->assertSame('ნინო ბერიძე', $order->first_name);
        $this->assertSame('555 12 34 56', $order->phone);
        $this->assertSame('cash', $order->payment_method);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('10598.00', $order->total);
        $this->assertNull($order->address);
        $this->assertMatchesRegularExpression('/^EL-\d{6}$/', $order->number);

        $item = $order->items->sole();
        $this->assertSame($iphone->id, $item->product_id);
        $this->assertSame(2, $item->quantity);
        $this->assertSame(['Desert Titanium', '1 TB'], $item->options);
        $this->assertSame('5299.00', $item->unit_price);
        $this->assertSame('10598.00', $item->total);
    }

    public function test_quick_order_validates_and_is_rate_limited(): void
    {
        $iphone = $this->iphone();

        Livewire::test('quick-order', ['productId' => $iphone->id])
            ->set('payment', 'bitcoin')
            ->call('submit')
            ->assertHasErrors(['name' => 'required', 'phone' => 'required', 'payment' => 'in']);

        $component = Livewire::test('quick-order', ['productId' => $iphone->id])
            ->set('name', 'Test')
            ->set('phone', '555123456');

        foreach (range(1, 3) as $attempt) {
            $component->call('submit');
        }

        $component->set('sent', false)->call('submit')->assertHasErrors('phone');

        $this->assertSame(3, Order::query()->where('source', 'quick')->count());
    }

    public function test_quick_order_prefills_and_links_signed_in_user(): void
    {
        $this->refreshApplicationWithLocale('en');

        $user = User::factory()->create(['name' => 'გიორგი', 'phone' => '599 00 00 00']);

        Livewire::actingAs($user)
            ->test('quick-order', ['productId' => $this->iphone()->id])
            ->assertSet('name', 'გიორგი')
            ->assertSet('phone', '599 00 00 00')
            ->call('submit');

        $order = Order::query()->sole();
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame($user->email, $order->email);

        $this->actingAs($user)
            ->get('/en/account?tab=orders')
            ->assertOk()
            ->assertSee('#'.$order->number)
            ->assertSee('iPhone 16 Pro');
    }
}
