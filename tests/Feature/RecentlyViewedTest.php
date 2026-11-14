<?php

namespace Tests\Feature;

use App\Support\RecentlyViewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecentlyViewedTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_viewed_products_are_remembered_and_shown_later(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $first = $this->get('/ka/product/apple-iphone-16-pro')->assertOk();
        $first->assertDontSee(__('product.recently_viewed'));

        $cookie = $first->getCookie(RecentlyViewed::COOKIE, false);
        $this->assertNotNull($cookie);

        $this->withCookie(RecentlyViewed::COOKIE, $cookie->getValue())
            ->get('/ka')
            ->assertOk()
            ->assertSee(__('product.recently_viewed'))
            ->assertSee('iPhone 16 Pro');
    }

    public function test_the_current_product_and_unknown_ids_are_left_out(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $iphone = $this->get('/ka/product/apple-iphone-16-pro')->getCookie(RecentlyViewed::COOKIE, false)->getValue();

        $this->withCookie(RecentlyViewed::COOKIE, $iphone.',999999,abc')
            ->get('/ka/product/apple-iphone-16-pro')
            ->assertOk()
            ->assertDontSee(__('product.recently_viewed'));
    }
}
