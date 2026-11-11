<?php

use App\Support\Cart;
use App\Services\Facebook\Pixel;
use App\Support\Catalog;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public array $items = [];

    public function mount(): void
    {
        $this->items = Cart::items();
    }

    #[On('cart-add')]
    public function add(int|string $id, int $qty = 1, array $options = []): void
    {
        $product = Catalog::find($id);

        if (! $product || ! $product['inStock']) {
            return;
        }

        $resolved = Catalog::resolveOptions($product, array_map('intval', $options));
        $key = Cart::key($product['id'], $resolved['ids']);

        $this->items = Cart::items();
        $this->items[$key] = ($this->items[$key] ?? 0) + max(1, min($qty, Cart::MAX_QUANTITY));
        $this->persist();

        $name = trim($product['name'].($resolved['labels'] ? ', '.implode(', ', $resolved['labels']) : ''));

        $this->dispatch('toast', message: __('shop.cart_drawer.added', ['name' => $name]), withCart: true);
        $this->dispatch('pixel', app(Pixel::class)->addToCart([
            'id' => $product['id'],
            'brand' => $product['brand'],
            'name' => $product['name'],
            'price' => (float) $product['price'],
            'qty' => $qty,
        ]));
    }

    #[On('cart-refresh')]
    public function refresh(): void
    {
        $this->items = Cart::items();
        $this->persist();
    }

    public function increment(string $key): void
    {
        if (isset($this->items[$key])) {
            $this->items[$key]++;
            $this->persist();
        }
    }

    public function decrement(string $key): void
    {
        if (isset($this->items[$key])) {
            $this->items[$key]--;
            $this->persist();
        }
    }

    public function remove(string $key): void
    {
        unset($this->items[$key]);
        $this->persist();
    }

    #[Computed]
    public function lines(): array
    {
        return Cart::lines($this->items);
    }

    #[Computed]
    public function total(): int
    {
        return (int) collect($this->lines)->sum('subtotal');
    }

    #[Computed]
    public function count(): int
    {
        return (int) array_sum($this->items);
    }

    protected function persist(): void
    {
        $this->items = Cart::store($this->items);

        unset($this->lines, $this->total, $this->count);

        $this->dispatch('cart-updated', items: (object) $this->items);
    }
};
?>

<div x-init="$store.cart.items = @js((object) $items)">
    <button
        type="button"
        x-on:click="$store.ui.cart = true"
        aria-label="{{ __('shop.cart') }}"
        class="hidden h-[52px] items-center gap-2.5 rounded-full bg-tint pr-2 pl-2 text-left text-navy hover:bg-tint-strong sm:flex lg:pr-[18px]"
    >
        <span class="relative grid size-9 place-items-center rounded-full bg-brand text-white">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 7h12l-1 13H7z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
            <span class="absolute -top-[5px] -right-[5px] h-[18px] min-w-[18px] rounded-[9px] border-2 border-tint bg-navy px-[5px] text-center text-[10px] leading-[14px] font-bold text-white">{{ $this->count }}</span>
        </span>
        <span class="hidden flex-col gap-0.5 leading-[1.1] lg:flex">
            <span class="text-[11px] font-bold tracking-[0.4px] text-brand uppercase">{{ __('shop.cart') }}</span>
            <span class="font-display text-sm font-extrabold">{{ Catalog::money($this->total) }}</span>
        </span>
    </button>

    <template x-teleport="body">
        <div x-show="$store.ui.cart" x-cloak>
            <div x-on:click="$store.ui.cart = false" class="fixed inset-0 z-50 animate-fade bg-navy/40"></div>
            <aside class="fixed inset-y-0 right-0 z-[51] flex w-[420px] max-w-screen animate-slide-left flex-col bg-white shadow-[-20px_0_60px_rgba(10,22,51,.18)]">
                <div class="flex items-center justify-between border-b border-line px-6 py-[22px]">
                    <span class="text-lg font-extrabold tracking-[0.4px] uppercase">{{ __('shop.cart') }} · {{ $this->count }}</span>
                    <button type="button" x-on:click="$store.ui.cart = false" aria-label="{{ __('shop.close') }}" class="size-10 rounded-[10px] bg-[#F1F5FC] text-xl text-navy">×</button>
                </div>

                @if ($this->count > 0)
                    <div class="flex flex-col gap-2 bg-field px-6 py-3.5 text-[13px]">
                        <span class="font-semibold text-navy">
                            @if ($this->total >= Catalog::FREE_SHIPPING_FROM)
                                {{ __('shop.cart_drawer.free_active') }}
                            @else
                                {{ __('shop.cart_drawer.left_for_free', ['amount' => Catalog::money(Catalog::FREE_SHIPPING_FROM - $this->total)]) }}
                            @endif
                        </span>
                        <div class="h-1.5 overflow-hidden rounded-[3px] bg-line">
                            <div class="h-full rounded-[3px] bg-linear-to-r from-brand to-sky transition-[width] duration-400" style="width: {{ min(100, round($this->total / Catalog::FREE_SHIPPING_FROM * 100)) }}%"></div>
                        </div>
                    </div>
                @endif

                <div class="flex flex-1 flex-col overflow-auto px-6 py-3">
                    @forelse ($this->lines as $line)
                        <div wire:key="cart-line-{{ $line['key'] }}" class="grid grid-cols-[64px_minmax(0,1fr)_auto] items-center gap-3.5 border-b border-line-soft py-3.5">
                            <a href="{{ $line['url'] }}" wire:navigate x-on:click="$store.ui.cart = false" class="grid size-16 place-items-center overflow-hidden rounded-xl bg-[#F1F5FC] font-display text-lg font-extrabold text-brand hover:text-brand">
                                @if ($line['image'])
                                    <img src="{{ $line['image'] }}" alt="" class="size-full object-contain">
                                @else
                                    {{ $line['initial'] }}
                                @endif
                            </a>
                            <div class="flex min-w-0 flex-col gap-1.5">
                                <div class="flex items-start justify-between gap-2">
                                    <span class="flex min-w-0 flex-col gap-0.5"><a href="{{ $line['url'] }}" wire:navigate x-on:click="$store.ui.cart = false" class="text-sm leading-[1.35] font-semibold text-navy hover:text-brand">{{ $line['name'] }}</a>@if ($line['variant'])<span class="block text-xs font-medium text-subtle">{{ $line['variant'] }}</span>@endif</span>
                                    <button type="button" wire:click="remove('{{ $line['key'] }}')" aria-label="{{ __('shop.cart_drawer.remove') }}" class="grid size-7 shrink-0 place-items-center rounded-lg text-subtle hover:bg-[#FDECEC] hover:text-sale">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg>
                                    </button>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button type="button" wire:click="decrement('{{ $line['key'] }}')" class="size-[30px] rounded-lg border border-line bg-white text-base text-navy">−</button>
                                    <span class="min-w-[26px] text-center text-sm font-bold">{{ $line['quantity'] }}</span>
                                    <button type="button" wire:click="increment('{{ $line['key'] }}')" class="size-[30px] rounded-lg border border-line bg-white text-base text-navy">+</button>
                                </div>
                            </div>
                            <span class="font-display text-base font-extrabold">{{ $line['lineLabel'] }}</span>
                        </div>
                    @empty
                        <div class="m-auto flex flex-col items-center gap-2 text-center text-sm text-muted">
                            <span class="mb-1.5 grid size-[72px] place-items-center rounded-full bg-tint text-brand">
                                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 7h12l-1 13H7z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
                            </span>
                            <span class="text-[17px] font-bold text-navy">{{ __('shop.cart_drawer.empty_title') }}</span>
                            {{ __('shop.cart_drawer.empty_text') }}
                            <button type="button" x-on:click="$store.ui.cart = false" class="mt-2.5 h-11 rounded-full bg-brand px-[22px] text-sm font-bold text-white hover:bg-brand-dark">{{ __('shop.cart_drawer.continue') }}</button>
                        </div>
                    @endforelse
                </div>

                <div class="flex flex-col gap-3.5 border-t border-line px-6 pt-5 pb-6">
                    <div class="flex justify-between text-sm text-muted">
                        <span>{{ __('shop.cart_drawer.shipping') }}</span>
                        <span>
                            @if ($this->total === 0)
                                —
                            @elseif ($this->total >= Catalog::FREE_SHIPPING_FROM)
                                {{ __('shop.cart_drawer.free') }}
                            @else
                                {{ Catalog::money(Catalog::SHIPPING_PRICE) }}
                            @endif
                        </span>
                    </div>
                    <div class="flex items-baseline justify-between">
                        <span class="font-bold">{{ __('shop.cart_drawer.total') }}</span>
                        <span class="font-display text-2xl font-extrabold">{{ Catalog::money($this->total) }}</span>
                    </div>
                    <a href="{{ route('checkout') }}" wire:navigate x-on:click="$store.ui.cart = false" class="grid h-[52px] place-items-center rounded-full bg-brand text-[15px] font-bold text-white shadow-[0_10px_24px_-8px_rgba(11,99,246,.6)] hover:bg-brand-dark hover:text-white">{{ __('shop.cart_drawer.checkout') }}</a>
                    <span class="flex items-center justify-center gap-1.5 text-xs text-subtle">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                        {{ __('shop.cart_drawer.secure') }}
                    </span>
                </div>
            </aside>
        </div>
    </template>
</div>
