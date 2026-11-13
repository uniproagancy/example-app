<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function jsonLd(string $html): array
    {
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $match);

        return collect(json_decode($match[1] ?? '{}', true)['@graph'] ?? [])->keyBy('@type')->all();
    }

    public function test_product_page_has_meta_open_graph_and_product_schema(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $response = $this->get('/ka/product/apple-iphone-16-pro?utm_source=fb')
            ->assertOk()
            ->assertSee('<title>Apple iPhone 16 Pro — ფასი, მახასიათებლები | ELIO.GE</title>', false)
            ->assertSee('<link rel="canonical" href="'.url('/ka/product/apple-iphone-16-pro').'">', false)
            ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false)
            ->assertSee('<meta property="og:type" content="product">', false)
            ->assertSee('<meta property="product:price:currency" content="GEL">', false)
            ->assertSee('hreflang="x-default" href="'.url('/ka/product/apple-iphone-16-pro').'"', false)
            ->assertSee('<meta name="description" content="Apple iPhone 16 Pro — ', false);

        $schema = $this->jsonLd($response->getContent());

        $this->assertSame('Apple iPhone 16 Pro', $schema['Product']['name']);
        $this->assertSame('ip16', $schema['Product']['sku']);
        $this->assertSame('Apple', $schema['Product']['brand']['name']);
        $this->assertSame('GEL', $schema['Product']['offers']['priceCurrency']);
        $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $schema['Product']['offers']['price']);
        $this->assertStringStartsWith('https://schema.org/', $schema['Product']['offers']['availability']);
        $this->assertSame('ELIO.GE', $schema['Organization']['name']);
        $this->assertSame('iPhone 16 Pro', last($schema['BreadcrumbList']['itemListElement'])['name']);
        $this->assertGreaterThan(2, count($schema['BreadcrumbList']['itemListElement']));
    }

    public function test_home_has_website_search_schema_and_a_heading(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $response = $this->get('/ka')
            ->assertOk()
            ->assertSee('<title>ELIO.GE — ტექნიკის ონლაინ მაღაზია საქართველოში</title>', false)
            ->assertSee('<h1 class="sr-only" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0">', false)
            ->assertSee('<meta property="og:image" content="http', false);

        $schema = $this->jsonLd($response->getContent());

        $this->assertSame(url('/ka/catalog').'?q={search_term_string}', $schema['WebSite']['potentialAction']['target']['urlTemplate']);
    }

    public function test_filtered_lists_point_to_the_clean_url_and_private_pages_are_not_indexed(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $this->get('/ka/categories/gaming?sort=price_asc&brand=Sony')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/ka/categories/gaming').'">', false)
            ->assertSee('"@type":"BreadcrumbList"', false);

        $this->get('/ka/catalog?q=iphone')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertDontSee('rel="canonical"', false)
            ->assertDontSee('application/ld+json', false);

        $this->withSession(['cart' => [(string) Product::query()->where('sku', 'jbl')->value('id') => 1]])
            ->get('/ka/checkout')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_text_pages_use_their_meta_description(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $page = Page::query()->active()->firstOrFail();
        $page->translations()->where('locale', 'ka')->update(['meta_description' => 'მიწოდება <b>მთელ</b> საქართველოში']);

        $this->get('/ka/info/'.$page->slug)
            ->assertOk()
            ->assertSee('<meta name="description" content="მიწოდება მთელ საქართველოში">', false);
    }

    public function test_robots_txt_lists_the_sitemap_and_hides_private_areas(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->assertSee('Disallow: /admin')
            ->assertSee('Disallow: /ka/checkout')
            ->assertSee('Disallow: /en/account')
            ->assertSee('Sitemap: '.url('/sitemap.xml'));
    }

    public function test_sitemap_lists_pages_categories_and_products_in_every_language(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();

        $this->assertStringStartsWith('application/xml', $response->headers->get('Content-Type'));

        $xml = simplexml_load_string($response->getContent());
        $locs = collect(iterator_to_array($xml->url, false))->map(fn ($url) => (string) $url->loc);

        $this->assertContains(url('/ka'), $locs);
        $this->assertContains(url('/en/contact'), $locs);
        $this->assertContains(url('/ka/categories/gaming'), $locs);
        $this->assertContains(url('/en/categories/gaming'), $locs);
        $this->assertContains(url('/ka/product/apple-iphone-16-pro'), $locs);
        $this->assertContains(url('/ka/info/'.Page::query()->active()->value('slug')), $locs);
        $this->assertFalse($locs->contains(fn (string $loc) => str_contains($loc, 'checkout') || str_contains($loc, 'account')));
        $this->assertSame($locs->count(), $locs->unique()->count());
        $this->assertStringContainsString('hreflang="x-default"', $response->getContent());
    }

    public function test_admin_sets_the_default_description_and_search_console_code(): void
    {
        $this->refreshApplicationWithLocale('ka');
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageSettings::class)
            ->fillForm([
                'seo_description_ka' => 'საუკეთესო ტექნიკა საუკეთესო ფასად',
                'google_site_verification' => 'abc123',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('abc123', Setting::get('google_site_verification'));

        $this->get('/ka/about')
            ->assertOk()
            ->assertSee('<meta name="google-site-verification" content="abc123">', false);

        $this->get('/ka')
            ->assertSee('<meta name="description" content="საუკეთესო ტექნიკა საუკეთესო ფასად">', false);
    }
}
