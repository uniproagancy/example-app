<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Models\Setting;
use App\Models\User;
use App\Support\Site;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MessengerButtonTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_the_button_is_hidden_without_a_facebook_page(): void
    {
        $this->refreshApplicationWithLocale('ka');
        Setting::put('facebook', null);
        Setting::put('messenger', null);

        $this->assertNull(Site::messenger());
        $this->get('/ka')->assertOk()->assertDontSee('m.me/', false);
    }

    public function test_the_link_is_built_from_the_facebook_page(): void
    {
        $this->refreshApplicationWithLocale('ka');
        Setting::put('messenger', null);

        foreach ([
            'https://www.facebook.com/elivo.ge' => 'https://m.me/elivo.ge',
            'https://facebook.com/elivo.ge/' => 'https://m.me/elivo.ge',
            'https://www.facebook.com/profile.php?id=61550000000000' => 'https://m.me/61550000000000',
            'https://www.facebook.com/people/Elivo/61550000000001/' => 'https://m.me/61550000000001',
            'https://www.instagram.com/elivo.ge' => null,
            'https://www.facebook.com/groups/elivo/posts/1' => null,
        ] as $page => $expected) {
            Setting::put('facebook', $page);

            $this->assertSame($expected, Site::messenger(), $page);
        }

        Setting::put('facebook', 'https://www.facebook.com/elivo.ge');

        $this->get('/ka')
            ->assertOk()
            ->assertSee('href="https://m.me/elivo.ge"', false)
            ->assertSee(__('shop.messenger', [], 'ka'));
    }

    public function test_an_own_messenger_link_wins_and_is_saved_from_settings(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Setting::put('facebook', 'https://www.facebook.com/elivo.ge');

        Livewire::test(ManageSettings::class)
            ->fillForm(['messenger' => 'http://m.me/other'])
            ->call('save')
            ->assertHasFormErrors(['messenger']);

        Livewire::test(ManageSettings::class)
            ->fillForm(['messenger' => 'https://m.me/elivo.shop'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('https://m.me/elivo.shop', Site::messenger());
    }
}
