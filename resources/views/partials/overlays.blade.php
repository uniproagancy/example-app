<nav aria-label="{{ __('shop.nav.home') }}" class="fixed inset-x-0 bottom-0 z-[46] flex items-stretch border-t border-line bg-white/97 px-2 font-caps pt-1 pb-[calc(4px+env(safe-area-inset-bottom))] backdrop-blur-md sm:hidden">
    <a href="{{ route('home') }}" wire:navigate class="flex min-h-[52px] min-w-0 flex-1 flex-col items-center justify-center gap-[3px] py-1 text-[11px] font-bold {{ request()->routeIs('home') ? 'text-brand' : 'text-muted' }}">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11 12 4l9 7"/><path d="M5 10v10h14V10"/></svg>
        {{ __('shop.mobile.home') }}
    </a>
    <button type="button" x-on:click="$dispatch('open-catalog')" class="flex min-h-[52px] min-w-0 flex-1 flex-col items-center justify-center gap-[3px] py-1 text-[11px] font-bold text-muted">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        {{ __('shop.mobile.catalog') }}
    </button>
    <button type="button" x-on:click="$store.ui.cart = true" class="flex min-h-[52px] min-w-0 flex-1 flex-col items-center justify-center gap-[3px] py-1 text-[11px] font-bold text-muted">
        <span class="relative grid">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 7h12l-1 13H7z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
            <span
                x-show="Object.values($store.cart.items).reduce((a, b) => a + b, 0) > 0"
                x-cloak
                x-text="Object.values($store.cart.items).reduce((a, b) => a + b, 0)"
                class="absolute -top-1 -right-[9px] h-[18px] min-w-[18px] rounded-[9px] border-2 border-white bg-brand px-[5px] text-center text-[10px] leading-[14px] font-bold text-white"
            ></span>
        </span>
        {{ __('shop.mobile.cart') }}
    </button>
    @auth
        <a href="{{ route('account') }}" wire:navigate class="flex min-h-[52px] min-w-0 flex-1 flex-col items-center justify-center gap-[3px] py-1 text-[11px] font-bold {{ request()->routeIs('account') ? 'text-brand' : 'text-muted' }}">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>
            {{ __('shop.mobile.account') }}
        </a>
    @else
        <button type="button" x-on:click="$store.ui.auth = true" class="flex min-h-[52px] min-w-0 flex-1 flex-col items-center justify-center gap-[3px] py-1 text-[11px] font-bold text-muted">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>
            {{ __('shop.mobile.account') }}
        </button>
    @endauth
</nav>

<div x-data="stickyHeader">
    <button
        type="button"
        x-show="showTop"
        x-cloak
        x-transition.opacity
        x-on:click="window.scrollTo({ top: 0, behavior: 'smooth' })"
        aria-label="{{ __('shop.to_top') }}"
        class="fixed right-6 bottom-[calc(84px+env(safe-area-inset-bottom))] z-45 grid size-12 place-items-center rounded-full bg-navy text-white shadow-[0_12px_28px_-8px_rgba(10,22,51,.5)] hover:bg-brand sm:bottom-6"
    >
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m6 14 6-6 6 6"/></svg>
    </button>
</div>

<div
    x-show="$store.toast.message"
    x-cloak
    class="fixed bottom-[calc(84px+env(safe-area-inset-bottom))] left-1/2 z-[60] flex -translate-x-1/2 animate-toast items-center gap-3.5 rounded-[14px] bg-navy px-5 py-3.5 text-sm font-semibold text-white shadow-[0_12px_32px_rgba(10,22,51,.3)] sm:bottom-7"
    role="status"
>
    <span x-text="$store.toast.message"></span>
    <button type="button" x-show="$store.toast.withCart" x-on:click="$store.ui.cart = true; $store.toast.message = ''" class="p-0 text-sm font-bold text-sky">{{ __('shop.cart_drawer.view') }}</button>
</div>

@include('partials.catalog-drawer')
<livewire:auth-modal />
@auth
    <livewire:favorites />
@endauth
