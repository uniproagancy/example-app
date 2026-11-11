<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public static function localizedPages(): array
    {
        return [
            'ka home' => ['ka', '/ka', 'მთავარი'],
            'en home' => ['en', '/en', 'Home'],
            'ka about' => ['ka', '/ka/about', 'ჩვენ შესახებ'],
            'en about' => ['en', '/en/about', 'About us'],
        ];
    }

    #[DataProvider('localizedPages')]
    public function test_localized_pages_render_in_their_language(string $locale, string $uri, string $expectedText): void
    {
        $this->refreshApplicationWithLocale($locale);

        $this->get($uri)
            ->assertOk()
            ->assertSee('<html lang="'.$locale.'"', false)
            ->assertSee($expectedText)
            ->assertSee('hreflang="ka"', false)
            ->assertSee('hreflang="en"', false)
            ->assertDontSee('hreflang="ru"', false);
    }

    public function test_russian_is_no_longer_available(): void
    {
        $this->followingRedirects()
            ->get('/ru')
            ->assertNotFound()
            ->assertSee(__('errors.404.title', [], 'ka'));
    }

    public function test_root_redirects_to_default_locale(): void
    {
        $this->get('/')->assertRedirect(url('/ka'));
    }

    public function test_language_switcher_links_to_translated_urls(): void
    {
        $this->refreshApplicationWithLocale('en');

        $this->get('/en/about')
            ->assertOk()
            ->assertSee(url('/ka/about'), false);
    }

    public function test_livewire_update_route_is_localized(): void
    {
        $this->refreshApplicationWithLocale('en');

        $this->assertSame('/en', substr(route('livewire.update', absolute: false), 0, 3));
    }

    public function test_home_page_renders_store_sections(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $this->get('/ka')
            ->assertOk()
            ->assertSee('აქციები')
            ->assertSee('კატეგორიები')
            ->assertSee('iPhone 16 Pro')
            ->assertSee('id="sec-mobile"', false);
    }
}
