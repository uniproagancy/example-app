<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Support\Cart;
use App\Support\Catalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestPageCacheTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.stores.pages' => ['driver' => 'array', 'serialize' => true]]);
        $this->refreshApplicationWithLocale('ka');
        config(['cache.stores.pages' => ['driver' => 'array', 'serialize' => true]]);
    }

    public function test_a_clean_guest_gets_the_cached_page_with_their_own_csrf_token(): void
    {
        $this->get('/ka')->assertOk()->assertHeader('X-Page-Cache', 'miss');

        $hit = $this->get('/ka')->assertOk()->assertHeader('X-Page-Cache', 'hit');
        $html = $hit->getContent();

        $this->assertStringContainsString('<meta name="csrf-token" content="'.session()->token().'">', $html);
        $this->assertStringNotContainsString('__ELIO_PAGE_CSRF__', $html);
        $this->assertStringContainsString('<!--fresh:partials.pixel-->', $html);
        $this->assertStringContainsString('<!--fresh:partials.cookie-consent-->', $html);
        $this->assertStringContainsString('</html>', $html);

        Catalog::flush();

        $this->get('/ka')->assertOk()->assertHeader('X-Page-Cache', 'miss');
    }

    public function test_visitors_with_personal_state_never_get_or_fill_the_cache(): void
    {
        $this->get('/ka/catalog?all=1')->assertOk()->assertHeaderMissing('X-Page-Cache');

        $this->actingAs(User::factory()->create());
        $this->get('/ka')->assertOk()->assertHeaderMissing('X-Page-Cache');
        auth()->logout();

        $speaker = Product::query()->where('sku', 'jbl')->value('id');
        $this->withSession([Cart::SESSION_KEY => [(string) $speaker => 1]])
            ->get('/ka')
            ->assertOk()
            ->assertHeaderMissing('X-Page-Cache');
    }

    public function test_product_pages_are_not_cached(): void
    {
        $this->get('/ka/product/apple-iphone-16-pro')->assertOk()->assertHeaderMissing('X-Page-Cache');
    }
}
