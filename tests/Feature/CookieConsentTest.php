<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Models\Setting;
use App\Models\User;
use App\Services\Facebook\Pixel;
use App\Support\Consent;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CookieConsentTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_no_banner_or_trackers_without_analytics_ids(): void
    {
        $this->refreshApplicationWithLocale('ka');
        config(['services.facebook.pixel_id' => null]);

        $this->get('/ka')
            ->assertOk()
            ->assertDontSee('googletagmanager.com', false)
            ->assertDontSee(__('shop.consent.accept'));
    }

    public function test_ga4_loads_with_consent_mode_and_the_banner_asks_once(): void
    {
        $this->refreshApplicationWithLocale('ka');
        config(['services.facebook.pixel_id' => null]);
        Setting::put('ga4_id', 'G-ABC123XYZ');

        $this->get('/ka')
            ->assertOk()
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-ABC123XYZ', false)
            ->assertSee("gtag('consent', 'default'", false)
            ->assertSee(__('shop.consent.accept'));

        $this->withUnencryptedCookie(Consent::COOKIE, Consent::GRANTED)
            ->get('/ka')
            ->assertSee('googletagmanager.com', false)
            ->assertDontSee(__('shop.consent.accept'));
    }

    public function test_pixel_id_comes_from_settings_and_declining_stops_server_events(): void
    {
        $this->refreshApplicationWithLocale('ka');
        config(['services.facebook.pixel_id' => '111111']);
        Setting::put('facebook_pixel_id', '222222222');

        $this->get('/ka')
            ->assertSee('"222222222"', false)
            ->assertSee("fbq('consent'", false)
            ->assertSee(__('shop.consent.accept'));

        $this->assertTrue(app(Pixel::class)->consented());

        $this->withUnencryptedCookie(Consent::COOKIE, Consent::DENIED)->get('/ka')->assertDontSee(__('shop.consent.accept'));
        $this->assertFalse(app(Pixel::class)->consented());
    }

    public function test_admin_saves_analytics_ids(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageSettings::class)
            ->fillForm(['ga4_id' => 'UA-123'])
            ->call('save')
            ->assertHasFormErrors(['ga4_id']);

        Livewire::test(ManageSettings::class)
            ->fillForm(['ga4_id' => 'g-abc123xyz', 'facebook_pixel_id' => '123456789'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('G-ABC123XYZ', Setting::get('ga4_id'));
        $this->assertSame('123456789', Setting::get('facebook_pixel_id'));
    }
}
