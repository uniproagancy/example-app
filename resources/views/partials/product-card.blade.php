<article
    data-card
    x-data="{ id: @js($product['id']) }"
    @isset($filter) x-show="visible(@js($product['sub']))" @endisset
    class="relative flex flex-col rounded-[18px] border border-line-soft bg-white px-1.5 pt-1.5 pb-3 transition duration-200 hover:-translate-y-0.5 hover:border-transparent hover:shadow-[0_18px_40px_-12px_rgba(10,22,51,.16)] sm:px-2 sm:pt-2 sm:pb-4"
>
    <div class="relative aspect-[4/4.3] w-full overflow-hidden rounded-[14px] bg-card">
        <a href="{{ $product['url'] }}" wire:navigate tabindex="-1" aria-hidden="true" class="absolute inset-0 grid place-items-center">
            @if ($product['image'])
                <img src="{{ $product['image'] }}" alt="" loading="lazy" class="size-full object-contain p-3">
            @else
                <span class="font-display text-[72px] leading-none font-extrabold text-brand/12 select-none">{{ mb_substr($product['brand'], 0, 1) }}</span>
            @endif
        </a>
        @if ($product['badge'])
            <span @class([
                'pointer-events-none absolute top-2.5 left-2.5 h-[22px] rounded-md px-2 text-[11px] leading-[22px] font-bold tracking-[0.2px] text-white',
                'bg-brand' => $product['isNew'],
                'bg-sale' => ! $product['isNew'],
            ])>{{ $product['badge'] }}</span>
        @endif
        <button
            type="button"
            data-reveal
            x-on:click="$store.favorites.toggle(id, @js(__('shop.favorites_added')), @js(__('shop.favorites_removed')))"
            aria-label="{{ __('shop.to_favorites') }}"
            class="absolute top-2 right-2 grid size-8 place-items-center rounded-full bg-white transition"
            :class="$store.favorites.has(id) ? 'opacity-100 text-sale' : 'opacity-0 text-muted'"
        >
            <svg width="16" height="16" viewBox="0 0 24 24" :fill="$store.favorites.has(id) ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8z"/></svg>
        </button>
    </div>

    <div class="flex flex-1 flex-col gap-1.5 px-1 pt-2.5 sm:px-2 sm:pt-3.5">
        <div class="flex items-center justify-between gap-2">
            <span class="font-ui text-[11px] font-bold tracking-[0.8px] text-subtle uppercase">{{ $product['brand'] }}</span>
        </div>
        <a href="{{ $product['url'] }}" wire:navigate class="line-clamp-2 min-h-10 text-sm leading-[1.45] font-semibold text-navy hover:text-brand">{{ $product['name'] }}</a>
        <span class="mt-0.5 inline-flex h-6 max-w-full items-center gap-1.5 self-start overflow-hidden rounded-full bg-tint pr-[9px] pl-[7px] text-[11px] font-semibold whitespace-nowrap text-brand">
            <svg class="shrink-0" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/></svg>
            <span class="hidden xl:inline">{{ __('shop.installment') }}</span>
            <span class="font-display font-extrabold">{{ $product['monthlyLabel'] }}</span> {{ __('shop.per_month') }}
        </span>
        <div class="mt-auto flex items-center justify-between gap-2.5 pt-3">
            <div class="flex flex-col gap-px">
                @if ($product['oldLabel'])
                    <span class="font-ui text-xs text-subtle line-through">{{ $product['oldLabel'] }}</span>
                @endif
                <span @class([
                    'font-display text-base font-extrabold tracking-[-0.3px] sm:text-[19px]',
                    'text-sale' => $product['oldLabel'],
                    'text-navy' => ! $product['oldLabel'],
                ])>{{ $product['priceLabel'] }}</span>
            </div>
            <button
                type="button"
                x-on:click="Livewire.dispatch('cart-add', { id })"
                :data-addbtn="$store.cart.qty(id) ? 'in' : 'idle'"
                aria-label="{{ __('shop.add_to_cart') }}"
                class="flex h-9 min-w-9 items-center justify-center gap-[5px] rounded-[10px] px-2 text-[13px] font-bold transition-colors sm:h-10 sm:min-w-10 sm:px-3"
                :class="$store.cart.qty(id) ? 'bg-ok-soft text-ok' : 'bg-tint text-brand'"
            >
                <template x-if="$store.cart.qty(id)">
                    <span class="flex items-center gap-[5px]">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg>
                        <span x-text="$store.cart.qty(id)"></span>
                    </span>
                </template>
                <template x-if="! $store.cart.qty(id)">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6"/><circle cx="10" cy="20.5" r="1.2"/><circle cx="17" cy="20.5" r="1.2"/></svg>
                </template>
            </button>
        </div>
    </div>
</article>
