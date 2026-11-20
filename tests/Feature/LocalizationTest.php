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
            'home' => ['/ka', 'მთავარი'],
            'about' => ['/ka/about', 'ჩვენ შესახებ'],
        ];
    }

    #[DataProvider('localizedPages')]
    public function test_pages_render_in_georgian_only(string $uri, string $expectedText): void
    {
        $this->refreshApplicationWithLocale('ka');

        $this->get($uri)
            ->assertOk()
            ->assertSee('<html lang="ka"', false)
            ->assertSee($expectedText)
            ->assertDontSee('hreflang="en"', false)
            ->assertDontSee('hreflang="ru"', false)
            ->assertDontSee('>ENG<', false);
    }

    public function test_english_addresses_move_to_the_georgian_page(): void
    {
        $this->get('/en')->assertRedirect(url('/ka'))->assertStatus(301);
        $this->get('/en/about?ref=1')->assertRedirect(url('/ka/about?ref=1'))->assertStatus(301);
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

    public function test_livewire_update_route_is_localized(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $this->assertSame('/ka', substr(route('livewire.update', absolute: false), 0, 3));
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
