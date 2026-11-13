<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Product;
use App\Models\Setting;
use App\Support\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_header_navigation_comes_from_database(): void
    {
        $this->refreshApplicationWithLocale('en');

        MenuItem::query()->create([
            'location' => 'header',
            'title' => ['ka' => 'ბლოგი', 'en' => 'Blog'],
            'url' => '/blog',
            'position' => 99,
        ]);

        MenuItem::query()->where('route_name', 'about')->update(['is_active' => false]);
        cache()->forget(MenuItem::CACHE_KEY);

        $response = $this->get('/en')
            ->assertOk()
            ->assertSee('Blog')
            ->assertSee(url('/en/blog'), false);

        $this->assertSame(1, substr_count($response->getContent(), 'href="'.url('/en/about').'"'));
    }

    public function test_site_name_comes_from_settings(): void
    {
        $this->refreshApplicationWithLocale('ka');

        Setting::put('site_name', 'TECHNO.COM');

        $this->assertSame(['TECHNO', '.COM'], Site::nameParts());

        $this->get('/ka')
            ->assertOk()
            ->assertSee('<title>TECHNO.COM — ტექნიკის ონლაინ მაღაზია საქართველოში</title>', false)
            ->assertDontSee('ELIO.GE');
    }

    public function test_cart_adds_products_and_calculates_total(): void
    {
        $speaker = Product::query()->where('sku', 'jbl')->value('id');

        Livewire::test('cart')
            ->dispatch('cart-add', id: $speaker)
            ->dispatch('cart-add', id: $speaker)
            ->assertDispatched('cart-updated')
            ->assertDispatched('toast')
            ->dispatch('cart-add', id: 999999)
            ->assertSet('items', [$speaker => 2])
            ->assertSee('898 ₾')
            ->dispatch('cart-add', id: $speaker, qty: 3)
            ->assertSet('items', [$speaker => 5])
            ->call('decrement', $speaker)
            ->assertSet('items', [$speaker => 4])
            ->call('remove', $speaker)
            ->assertSet('items', []);

        $this->assertSame([], session('cart'));
    }

    public function test_out_of_stock_products_cannot_be_added_to_cart(): void
    {
        $product = Product::query()->where('sku', 'jbl')->first();
        $product->update(['stock' => 0]);

        Livewire::test('cart')
            ->dispatch('cart-add', id: $product->id)
            ->assertSet('items', []);
    }
}
