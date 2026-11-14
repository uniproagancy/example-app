<?php

namespace Tests\Feature;

use App\Filament\Resources\Redirects\Pages\ManageRedirects;
use App\Filament\Resources\Redirects\RedirectResource;
use App\Models\Product;
use App\Models\Redirect;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RedirectsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_a_missing_page_follows_an_active_redirect_and_counts_the_hit(): void
    {
        $redirect = Redirect::query()->create(['from_path' => 'https://elivo.ge/ka/old-page/?x=1', 'to_url' => '/ka/catalog']);

        $this->assertSame('/ka/old-page', $redirect->from_path);

        $this->get('/ka/old-page')->assertStatus(301)->assertRedirect(url('/ka/catalog'));
        $this->get('/ka/OLD-PAGE/')->assertStatus(301);

        $this->assertSame(2, $redirect->fresh()->hits);
        $this->assertNotNull($redirect->fresh()->last_hit_at);

        $redirect->update(['is_active' => false]);
        $this->get('/ka/old-page')->assertNotFound();
    }

    public function test_existing_pages_are_never_redirected(): void
    {
        Redirect::query()->create(['from_path' => '/ka/catalog', 'to_url' => '/ka', 'status_code' => 302]);

        $this->refreshApplicationWithLocale('ka');

        $this->get('/ka/catalog')->assertOk();
    }

    public function test_renaming_a_product_slug_keeps_the_old_link_working(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $product = Product::query()->where('sku', 'ip16')->sole();
        $product->translations()->where('locale', 'en')->sole()->update(['slug' => 'apple-iphone-16-pro-en']);
        $translation = $product->translations()->where('locale', 'ka')->sole();
        $translation->update(['slug' => 'iphone-16-pro-axali']);

        $this->get('/en/product/apple-iphone-16-pro')->assertStatus(301)->assertRedirect(url('/en/product/apple-iphone-16-pro-en'));

        $this->get('/ka/product/apple-iphone-16-pro')->assertStatus(301)->assertRedirect(url('/ka/product/iphone-16-pro-axali'));
        $this->get('/ka/product/iphone-16-pro-axali')->assertOk();

        $translation->update(['slug' => 'iphone-16-pro-bolo']);

        $this->assertSame('/ka/product/iphone-16-pro-bolo', Redirect::query()->where('from_path', '/ka/product/apple-iphone-16-pro')->value('to_url'));
        $this->assertTrue(Redirect::query()->where('from_path', '/ka/product/iphone-16-pro-axali')->where('is_automatic', true)->exists());

        $translation->update(['slug' => 'apple-iphone-16-pro']);

        $this->assertFalse(Redirect::query()->where('from_path', '/ka/product/apple-iphone-16-pro')->exists());
        $this->get('/ka/product/apple-iphone-16-pro')->assertOk();
    }

    public function test_admin_manages_redirects(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->get(RedirectResource::getUrl())->assertOk();

        Livewire::test(ManageRedirects::class)
            ->callAction('create', ['from_path' => '/ka', 'to_url' => '/en'])
            ->assertHasActionErrors(['from_path']);

        Livewire::test(ManageRedirects::class)
            ->callAction('create', ['from_path' => '/ka/sale-2025', 'to_url' => '/ka/catalog', 'status_code' => 302])
            ->assertHasNoActionErrors();

        $this->assertSame(302, Redirect::query()->where('from_path', '/ka/sale-2025')->value('status_code'));

        $this->actingAs(User::factory()->operator()->create());
        $this->get(RedirectResource::getUrl())->assertForbidden();
    }
}
