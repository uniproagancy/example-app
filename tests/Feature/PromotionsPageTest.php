<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\MenuItem;
use App\Models\Product;
use App\Models\Promo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionsPageTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_lists_every_live_promotion_deal_and_discounted_product(): void
    {
        $this->refreshApplicationWithLocale('ka');

        Promo::query()->first()->update(['ends_at' => now()->addDays(5)->setTime(12, 0)]);
        Promo::query()->create([
            'title' => ['ka' => 'დასრულებული აქცია'],
            'is_active' => true,
            'ends_at' => now()->subDay(),
        ]);
        Promo::query()->create([
            'title' => ['ka' => 'გამორთული აქცია'],
            'is_active' => false,
        ]);

        $this->get('/ka/promotions')
            ->assertOk()
            ->assertSee(__('pages.promotions.heading'))
            ->assertSee('აუდიო კვირეული')
            ->assertSee('სმარტ სახლის კვირა')
            ->assertSee(__('pages.promotions.until', ['date' => now()->addDays(5)->format('d.m.Y')]))
            ->assertDontSee('დასრულებული აქცია')
            ->assertDontSee('გამორთული აქცია')
            ->assertSee(__('pages.promotions.deals'))
            ->assertSee('Galaxy Tab S10')
            ->assertSee(__('pages.promotions.sale'))
            ->assertSee('/ka/catalog?only%5B0%5D=sale', false)
            ->assertSee('<link rel="canonical" href="'.url('/ka/promotions').'">', false);
    }

    public function test_shows_an_empty_state_without_promotions(): void
    {
        $this->refreshApplicationWithLocale('en');

        Promo::query()->get()->each->delete();
        Deal::query()->get()->each->delete();
        Product::query()->update(['old_price' => null]);

        $this->get('/en/promotions')
            ->assertOk()
            ->assertSee(__('pages.promotions.empty'))
            ->assertDontSee(__('pages.promotions.campaigns').'</h2>', false);
    }

    public function test_page_is_linked_from_the_site_and_listed_in_the_sitemap(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $this->assertTrue(MenuItem::query()->where('route_name', 'promotions')->exists());

        $this->get('/ka')->assertOk()->assertSee(url('/ka/promotions'), false);
        $this->get('/sitemap.xml')->assertOk()->assertSee(url('/ka/promotions'), false);
    }
}
