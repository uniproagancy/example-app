<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\CategoryTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_missing_page_shows_branded_404_inside_the_site_layout(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $response = $this->get('/ka/does-not-exist')
            ->assertNotFound()
            ->assertSee('<title>გვერდი ვერ მოიძებნა — ELIO.GE</title>', false)
            ->assertSee(__('errors.404.text', [], 'ka'))
            ->assertSee('href="'.url('/ka/catalog').'"', false);

        CategoryTree::roots()->take(8)->each(fn (array $root) => $response->assertSee(url('/ka/categories/'.$root['slug']), false));
    }

    public function test_404_keeps_the_signed_in_header(): void
    {
        $this->refreshApplicationWithLocale('en');

        $user = User::factory()->create(['name' => 'Nino Beridze']);

        $this->actingAs($user)
            ->get('/en/does-not-exist')
            ->assertNotFound()
            ->assertSee('title="Nino Beridze"', false);
    }

    public function test_unknown_product_and_category_return_404(): void
    {
        $this->refreshApplicationWithLocale('en');

        $this->get('/en/product/no-such-product')->assertNotFound()->assertSee('Page not found');
        $this->get('/en/categories/no-such-category')->assertNotFound()->assertSee('Page not found');
    }

    public function test_server_errors_use_the_standalone_error_page(): void
    {
        $this->refreshApplicationWithLocale('en');

        Route::get('/en/__boom', fn () => throw new \RuntimeException('boom'))->middleware('web');
        config(['app.debug' => false]);

        $this->get('/en/__boom')
            ->assertStatus(500)
            ->assertSee('Something went wrong')
            ->assertSee(__('errors.reload', [], 'en'))
            ->assertDontSee('boom');
    }

    public function test_other_error_codes_are_translated(): void
    {
        $this->refreshApplicationWithLocale('ka');

        foreach ([403, 419, 429, 503] as $code) {
            Route::get("/ka/__error-$code", fn () => abort($code))->middleware('web');

            $this->get("/ka/__error-$code")
                ->assertStatus($code)
                ->assertSee(__("errors.$code.title", [], 'ka'))
                ->assertSee(__('errors.home', [], 'ka'));
        }
    }
}
