<?php

namespace Tests\Feature;

use App\Filament\Resources\TextPages\Pages\CreateTextPage;
use App\Filament\Resources\TextPages\Pages\EditTextPage;
use App\Filament\Resources\TextPages\TextPageResource;
use App\Models\Page;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class TextPagesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_seeded_pages_render_and_the_footer_links_to_them(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $this->get('/ka/info/delivery')
            ->assertOk()
            ->assertSee('<title>მიწოდების პირობები — ELIO.GE</title>', false)
            ->assertSee('<h3>ვადები</h3>', false)
            ->assertSee(url('/ka/info/warranty'), false)
            ->assertDontSee(url('/ka/info/returns'), false)
            ->assertSee(url('/ka/info/terms'), false);

        $this->get('/ka/info/nope')->assertNotFound();

        Page::query()->where('slug', 'warranty')->update(['is_active' => false]);
        $this->get('/ka/info/warranty')->assertNotFound();
    }

    public function test_admin_edits_a_page_and_adds_a_new_one(): void
    {
        $this->refreshApplicationWithLocale('ka');
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $delivery = Page::query()->where('slug', 'delivery')->sole();

        $this->get(TextPageResource::getUrl())->assertOk()->assertSee('მიწოდების პირობები');

        Livewire::test(EditTextPage::class, ['record' => $delivery->getRouteKey()])
            ->fillForm([
                'kaTranslation' => [
                    'title' => 'მიწოდება და ტარიფები',
                    'intro' => 'ახალი შესავალი',
                    'body' => '<p>ტარიფი <strong>5 ₾</strong>.</p><script>alert(1)</script>',
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('მიწოდება და ტარიფები', $delivery->fresh('translations')->translate('ka')->title);

        Livewire::test(CreateTextPage::class)
            ->fillForm([
                'slug' => 'about-delivery-zones',
                'is_active' => true,
                'in_footer' => true,
                'kaTranslation' => ['title' => 'მიწოდების ზონები', 'body' => '<p>თბილისი და რეგიონები</p>'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->get('/ka/info/about-delivery-zones')->assertOk()->assertSee('თბილისი და რეგიონები');

        $this->get('/ka/info/delivery')
            ->assertOk()
            ->assertSee('მიწოდება და ტარიფები')
            ->assertSee('ახალი შესავალი')
            ->assertSee('<strong>5 ₾</strong>', false)
            ->assertDontSee('alert(1)', false);
    }

    public function test_footer_survives_a_database_without_the_pages_table(): void
    {
        $this->refreshApplicationWithLocale('ka');

        Schema::drop('page_translations');
        Schema::drop('pages');
        Cache::forget(Page::CACHE_KEY);

        $this->get('/ka')->assertOk()->assertSee(__('shop.footer.delivery'));
    }
}
