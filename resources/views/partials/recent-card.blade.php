<article
    data-card
    x-data="{ id: @js($product['id']) }"
    class="relative flex h-full items-center gap-3.5 rounded-[18px] bg-white p-2.5 pr-3 shadow-[0_1px_2px_rgba(10,22,51,.05)] ring-1 ring-[#DCE6F7] hover:-translate-y-1 hover:shadow-[0_18px_36px_-18px_rgba(10,22,51,.28)] hover:ring-transparent"
>
    <a href="{{ $product['url'] }}" wire:navigate tabindex="-1" aria-hidden="true" class="relative grid size-[88px] shrink-0 place-items-center overflow-hidden rounded-[14px] bg-card sm:size-24">
        @if ($product['image'])
            <img src="{{ $product['image'] }}" alt="" loading="lazy" class="size-full object-contain p-2">
        @else
            <span class="font-display text-3xl leading-none font-extrabold text-brand/15 select-none">{{ mb_substr($product['brand'], 0, 1) }}</span>
        @endif
        @if ($product['badge'])
            <span @class([
                'pointer-events-none absolute top-1.5 left-1.5 h-[18px] rounded-[5px] px-1.5 text-[10px] leading-[18px] font-bold text-white',
                'bg-brand' => $product['isNew'],
                'bg-sale' => ! $product['isNew'],
            ])>{{ $product['badge'] }}</span>
        @endif
    </a>

    <div class="flex min-w-0 flex-1 flex-col gap-1">
        <span class="truncate font-ui text-[10px] font-bold tracking-[0.8px] text-subtle uppercase">{{ $product['brand'] }}</span>
        <a href="{{ $product['url'] }}" wire:navigate class="line-clamp-2 text-[13px] leading-[1.4] font-semibold text-navy hover:text-brand">{{ $product['name'] }}</a>
        <div class="mt-0.5 flex items-baseline gap-2">
            <span @class([
                'font-display text-base font-extrabold tracking-[-0.3px]',
                'text-sale' => $product['oldLabel'],
                'text-navy' => ! $product['oldLabel'],
            ])>{{ $product['priceLabel'] }}</span>
            @if ($product['oldLabel'])
                <span class="truncate font-ui text-[11px] text-subtle line-through">{{ $product['oldLabel'] }}</span>
            @endif
        </div>
    </div>

    <button
        type="button"
        x-on:click="Livewire.dispatch('cart-add', { id })"
        :data-addbtn="$store.cart.qty(id) ? 'in' : 'idle'"
        aria-label="{{ __('shop.add_to_cart') }}"
        class="grid size-9 shrink-0 place-items-center self-end rounded-full"
        :class="$store.cart.qty(id) ? 'bg-ok-soft text-ok' : 'bg-tint text-brand hover:bg-brand hover:text-white'"
    >
        <template x-if="$store.cart.qty(id)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg>
        </template>
        <template x-if="! $store.cart.qty(id)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6"/><circle cx="10" cy="20.5" r="1.2"/><circle cx="17" cy="20.5" r="1.2"/></svg>
        </template>
    </button>
</article>
