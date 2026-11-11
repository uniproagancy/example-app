<?php

namespace Tests\Feature;

use App\Models\Favorite;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Js;
use Livewire\Livewire;
use Tests\TestCase;

class FavoritesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_signed_in_user_can_add_and_remove_favorites(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->first();

        Livewire::actingAs($user)
            ->test('favorites')
            ->dispatch('favorite-toggle', id: $product->id, on: true)
            ->assertDispatched('favorites-updated', ids: [$product->id]);

        $this->assertDatabaseHas('favorites', ['user_id' => $user->id, 'product_id' => $product->id]);

        Livewire::actingAs($user)
            ->test('favorites')
            ->dispatch('favorite-toggle', id: $product->id, on: true)
            ->dispatch('favorite-toggle', id: $product->id, on: false)
            ->assertDispatched('favorites-updated', ids: []);

        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_guest_favorites_merge_into_account_and_skip_invalid_products(): void
    {
        $user = User::factory()->create();
        [$first, $second, $inactive] = Product::query()->take(3)->get()->all();
        $inactive->update(['is_active' => false]);

        Favorite::factory()->for($user)->create(['product_id' => $first->id]);

        Livewire::actingAs($user)
            ->test('favorites')
            ->dispatch('favorites-merge', ids: [$first->id, $second->id, $inactive->id, 999999]);

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $user->fresh()->favoriteIds());
    }

    public function test_guests_cannot_store_favorites(): void
    {
        Livewire::test('favorites')
            ->dispatch('favorite-toggle', id: Product::query()->value('id'), on: true)
            ->assertNotDispatched('favorites-updated');

        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_layout_exposes_favorites_and_account_lists_them(): void
    {
        $this->refreshApplicationWithLocale('en');

        $user = User::factory()->create();
        $product = Product::query()->where('sku', 'ip16')->first();
        Favorite::factory()->for($user)->create(['product_id' => $product->id]);

        $this->get('/en')->assertSee('window.elioFavorites = null', false);

        $this->actingAs($user)
            ->get('/en')
            ->assertSee('window.elioFavorites = '.Js::from([$product->id]), false);

        $wishProducts = Livewire::actingAs($user)
            ->test('pages::account')
            ->call('show', 'wishlist')
            ->viewData('wishProducts');

        $this->assertSame([$product->id], $wishProducts->pluck('id')->all());
    }
}
