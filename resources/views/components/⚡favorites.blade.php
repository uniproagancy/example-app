<?php

use App\Models\Favorite;
use App\Services\Facebook\Pixel;
use App\Support\Catalog;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    #[On('favorite-toggle')]
    public function toggle(int $id, bool $on): void
    {
        if ($on && ($product = Catalog::find($id))) {
            $this->dispatch('pixel', app(Pixel::class)->addToWishlist([
                'id' => $product['id'],
                'brand' => $product['brand'],
                'name' => $product['name'],
                'price' => (float) $product['price'],
            ]));
        }

        $user = Auth::user();

        if (! $user) {
            return;
        }

        if ($on) {
            $user->addFavorites([$id]);
        } else {
            $user->removeFavorite($id);
        }

        $this->dispatch('favorites-updated', ids: $user->favoriteIds());
    }

    #[On('favorites-merge')]
    public function merge(array $ids): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $user->addFavorites(array_slice($ids, 0, Favorite::MAX_PER_USER));

        $this->dispatch('favorites-updated', ids: $user->favoriteIds());
    }
};
?>

<div hidden></div>
