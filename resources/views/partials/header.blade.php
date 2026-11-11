@php
    use App\Support\Catalog;

    $searchProducts = Catalog::products()->map(fn ($product) => collect($product)->only(['id', 'url', 'image', 'brand', 'name', 'subLabel', 'priceLabel']))->values();
    $searchCategories = Catalog::searchableCategories();

    $navItems = App\Models\MenuItem::for('header');
    $highlightItems = App\Models\MenuItem::for('header_highlight');
@endphp

<header
    x-data="stickyHeader"
    class="sticky top-0 z-20 bg-white/95 backdrop-blur-md transition-shadow duration-300"
    :class="scrolled ? 'shadow-[0_8px_24px_-12px_rgba(10,22,51,.18)]' : 'shadow-[0_1px_0_#EEF2F8]'"
>
    <div
        class="mx-auto flex max-w-[1480px] flex-wrap items-center gap-2.5 px-4 transition-[padding] duration-300 sm:flex-nowrap sm:gap-3 sm:px-6 lg:gap-5 lg:px-8"
        :class="scrolled ? 'py-3' : 'pt-2.5 pb-2.5 sm:pt-[18px] sm:pb-3.5'"
    >
        <a href="{{ route('home') }}" wire:navigate class="shrink-0 hover:text-navy">
            @include('partials.logo')
        </a>

        <button
            type="button"
            x-on:click="window.dispatchEvent(new CustomEvent('open-catalog'))"
            :aria-expanded="$store.ui.catalog"
            aria-label="{{ __('shop.catalog') }}"
            title="{{ __('shop.catalog') }}"
            class="group ml-auto flex size-[42px] shrink-0 items-center justify-center rounded-full bg-linear-to-r from-brand to-brand-bright text-white shadow-[0_12px_24px_-14px_rgba(11,99,246,.9)] transition-[box-shadow,filter] duration-200 hover:shadow-[0_16px_30px_-12px_rgba(11,99,246,.95)] hover:brightness-110 focus-visible:ring-4 focus-visible:ring-brand/20 active:brightness-95 sm:ml-0 sm:size-12"
        >
            <span aria-hidden="true" class="flex h-3.5 w-[18px] flex-col justify-between">
                <span class="h-0.5 w-full rounded-full bg-current"></span>
                <span class="h-0.5 w-[60%] rounded-full bg-current transition-[width] duration-300 ease-out group-hover:w-full"></span>
                <span class="h-0.5 w-[80%] rounded-full bg-current transition-[width] duration-300 ease-out group-hover:w-full"></span>
            </span>
        </button>

        <div
            x-data="searchBox(@js($searchProducts), @js($searchCategories), @js(route('catalog')))"
            class="relative order-5 min-w-0 flex-[1_0_100%] sm:order-none sm:flex-1"
        >
            <div
                class="flex h-12 items-center gap-2.5 rounded-full border-[1.5px] pr-1.5 pl-[22px] transition-colors"
                :class="focused ? 'border-brand bg-white' : 'border-line bg-field'"
            >
                <input
                    type="search"
                    x-model="query"
                    x-on:focus="focused = true"
                    x-on:blur="focused = false"
                    x-on:keydown.enter="submit()"
                    placeholder="{{ __('shop.search_placeholder') }}"
                    aria-label="{{ __('shop.search') }}"
                    class="min-w-0 flex-1 border-0 bg-transparent text-sm text-navy outline-0 [&::-webkit-search-cancel-button]:hidden"
                >
                <button
                    type="button"
                    x-show="term"
                    x-cloak
                    x-on:click="clear()"
                    aria-label="{{ __('shop.clear') }}"
                    class="grid size-[30px] place-items-center rounded-full text-subtle hover:bg-line-soft hover:text-navy"
                >
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
                <button
                    type="button"
                    x-on:click="submit()"
                    aria-label="{{ __('shop.search') }}"
                    class="grid size-9 shrink-0 place-items-center rounded-full bg-navy text-white hover:bg-brand"
                >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                </button>
            </div>

            <div
                x-show="open"
                x-cloak
                x-on:mousedown.prevent
                class="absolute inset-x-0 top-[54px] z-40 animate-drop overflow-hidden rounded-2xl border border-line bg-white shadow-[0_24px_60px_-12px_rgba(10,22,51,.25)]"
            >
                <template x-if="categoryMatches.length">
                    <div class="flex flex-col gap-2 px-4 pt-3.5 pb-1.5">
                        <span class="text-[11px] font-bold tracking-[0.6px] text-subtle uppercase">{{ __('shop.categories_label') }}</span>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="category in categoryMatches" :key="category.id">
                                <button type="button" x-on:click="openCategory(category)" class="flex h-8 max-w-full items-center gap-1.5 rounded-full border border-line bg-white px-3 text-[13px] font-semibold text-navy hover:border-brand hover:text-brand">
                                    <span class="truncate" x-text="category.name"></span>
                                    <span x-show="category.parent" class="truncate text-[11px] font-medium text-subtle" x-text="category.parent"></span>
                                    <span x-show="category.count" class="text-[11px] font-medium text-subtle" x-text="category.count"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-if="productMatches.length">
                    <div class="flex flex-col px-2 pt-2.5 pb-1.5">
                        <span class="px-2 pt-1 pb-1.5 text-[11px] font-bold tracking-[0.6px] text-subtle uppercase">{{ __('shop.products_label') }}</span>
                        <template x-for="product in productMatches.slice(0, 5)" :key="product.id">
                            <div class="grid grid-cols-[44px_minmax(0,1fr)_auto_36px] items-center gap-3 rounded-xl p-2 hover:bg-field">
                                <div class="grid size-11 place-items-center overflow-hidden rounded-[10px] bg-[#F1F5FC] font-display text-[15px] font-extrabold text-brand">
                                    <template x-if="product.image">
                                        <img :src="product.image" :alt="product.name" loading="lazy" class="size-full bg-white object-contain p-1">
                                    </template>
                                    <template x-if="!product.image">
                                        <span x-text="(product.brand || product.name || '?')[0]"></span>
                                    </template>
                                </div>
                                <div class="flex min-w-0 flex-col gap-0.5">
                                    <span class="truncate text-sm font-semibold" x-text="product.name"></span>
                                    <span class="text-xs text-subtle" x-text="`${product.brand} · ${product.subLabel}`"></span>
                                </div>
                                <span class="font-display text-[15px] font-extrabold" x-text="product.priceLabel"></span>
                                <button type="button" x-on:click="jumpTo(product)" aria-label="{{ __('shop.go_to_product') }}" class="grid size-9 place-items-center rounded-full bg-tint text-brand hover:bg-brand hover:text-white">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </template>

                <template x-if="productMatches.length">
                    <button type="button" x-on:click="submit()" class="flex h-12 w-full items-center justify-center gap-2 border-t border-line-soft text-[13px] font-bold text-brand hover:bg-field">
                        <span x-text="@js(__('shop.all_results', ['count' => '__N__'])).replace('__N__', productMatches.length)"></span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </button>
                </template>

                <template x-if="!productMatches.length && !categoryMatches.length">
                    <div class="px-4 py-7 text-center text-sm text-muted" x-text="@js(__('shop.nothing_found', ['query' => '__Q__'])).replace('__Q__', query.trim())"></div>
                </template>
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-2.5">
            @auth
                <a href="{{ route('account', ['tab' => 'wishlist']) }}" wire:navigate aria-label="{{ __('shop.favorites') }}" class="relative grid size-[42px] place-items-center rounded-full bg-soft text-navy hover:bg-tint hover:text-brand sm:size-12">
            @else
                <button type="button" x-on:click="$store.ui.auth = true" aria-label="{{ __('shop.favorites') }}" class="relative grid size-[42px] place-items-center rounded-full bg-soft text-navy hover:bg-tint hover:text-brand sm:size-12">
            @endauth
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8z"/></svg>
                <span x-show="$store.favorites.count > 0" x-cloak x-text="$store.favorites.count" class="absolute -top-0.5 -right-0.5 h-[18px] min-w-[18px] rounded-[9px] border-2 border-white bg-navy px-[5px] text-center text-[10px] leading-[14px] font-bold text-white"></span>
            @auth
                </a>
                <a
                    href="{{ route('account') }}"
                    wire:navigate
                    aria-label="{{ __('shop.account') }}"
                    title="{{ auth()->user()->name }}"
                    class="relative hidden size-12 items-center justify-center rounded-full bg-soft text-navy hover:bg-tint hover:text-brand sm:flex"
                >
                    <span class="font-display text-base font-extrabold text-brand">{{ mb_substr(auth()->user()->initials(), 0, 1) }}</span>
                    <span class="absolute top-0.5 right-0.5 size-3 rounded-full border-2 border-white bg-[#1FB866]"></span>
                </a>
            @else
                </button>
                <button
                    type="button"
                    x-on:click="$store.ui.auth = true"
                    aria-label="{{ __('shop.account') }}"
                    title="{{ __('shop.auth.login') }}"
                    class="relative hidden size-12 items-center justify-center rounded-full bg-soft text-navy hover:bg-tint hover:text-brand sm:flex"
                >
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>
                </button>
            @endauth
            <livewire:cart />
        </div>
    </div>

    <div
        class="mx-auto hidden max-w-[1480px] items-center gap-4 overflow-hidden px-6 transition-all duration-300 sm:flex lg:px-8"
        :class="scrolled ? 'max-h-0 pb-0 opacity-0' : 'max-h-[60px] pb-3.5 opacity-100'"
    >
        <nav class="no-scrollbar font-caps flex min-w-0 flex-1 flex-nowrap items-center gap-1 overflow-x-auto text-[13px] font-bold tracking-[0.4px] whitespace-nowrap uppercase">
            @foreach ($navItems as $item)
                <a
                    href="{{ $item->href }}"
                    @if ($item->isPageLink()) wire:navigate @endif
                    @if ($item->isCurrent()) aria-current="page" @endif
                    @class([
                        'flex h-[38px] items-center rounded-full',
                        'bg-navy px-5 text-white hover:text-white' => $item->isCurrent(),
                        'px-[18px] text-navy hover:bg-soft hover:text-brand' => ! $item->isCurrent(),
                    ])
                >{{ $item->label }}</a>
            @endforeach
        </nav>
        @include('partials.language-switcher')
        @foreach ($highlightItems as $item)
            <a href="{{ $item->href }}" class="flex h-[38px] shrink-0 items-center rounded-full bg-tint px-5 font-caps text-[13px] font-bold tracking-[0.4px] text-brand uppercase hover:bg-brand hover:text-white">{{ $item->label }}</a>
        @endforeach
    </div>
</header>
