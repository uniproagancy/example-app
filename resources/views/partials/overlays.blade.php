<nav x-data aria-label="{{ __('shop.nav.home') }}" class="fixed inset-x-0 bottom-0 z-[46] flex items-stretch border-t border-line bg-white px-2 font-caps pt-1 pb-[calc(4px+env(safe-area-inset-bottom))] transform-gpu sm:hidden">
    <a href="{{ route('home') }}" wire:navigate class="flex min-h-[52px] min-w-0 flex-1 flex-col items-center justify-center gap-[3px] py-1 text-[11px] font-bold {{ request()->routeIs('home') ? 'text-brand' : 'text-muted' }}">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11 12 4l9 7"/><path d="M5 10v10h14V10"/></svg>
        {{ __('shop.mobile.home') }}
    </a>
    <button type="button" x-on:click="$dispatch('open-catalog')" class="flex min-h-[52px] min-w-0 flex-1 flex-col items-center justify-center gap-[3px] py-1 text-[11px] font-bold text-muted">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        {{ __('shop.mobile.catalog') }}
    </button>
    <button type="button" x-on:click="$store.ui.cart = true" class="flex min-h-[52px] min-w-0 flex-1 flex-col items-center justify-center gap-[3px] py-1 text-[11px] font-bold text-muted">
        <span data-cart-target class="relative grid">
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

@php($messenger = App\Support\Site::messenger())

@if ($messenger)
    <a
        href="{{ $messenger }}"
        target="_blank"
        rel="noopener noreferrer"
        aria-label="{{ __('shop.messenger') }}"
        title="{{ __('shop.messenger') }}"
        class="fixed right-6 bottom-[calc(84px+env(safe-area-inset-bottom))] z-45 grid size-14 place-items-center rounded-full bg-[linear-gradient(135deg,#00B2FF_0%,#006AFF_100%)] text-white shadow-[0_12px_28px_-8px_rgba(0,106,255,.6)] transition hover:scale-105 hover:text-white sm:bottom-6"
    >
        <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M.001 11.639C.001 4.949 5.241 0 12.001 0S24 4.95 24 11.639c0 6.689-5.24 11.638-12 11.638-1.21 0-2.38-.16-3.47-.46a.96.96 0 0 0-.64.05l-2.39 1.05a.96.96 0 0 1-1.35-.85l-.07-2.14a.97.97 0 0 0-.32-.68A11.39 11.39 0 0 1 .002 11.64zm8.32-2.19-3.52 5.6c-.35.53.32 1.139.82.75l3.79-2.87c.26-.2.6-.2.87 0l2.8 2.1c.84.63 2.04.4 2.6-.48l3.52-5.6c.35-.53-.32-1.13-.82-.75l-3.79 2.87c-.25.2-.6.2-.86 0l-2.8-2.1a1.8 1.8 0 0 0-2.61.48z"/></svg>
    </a>
@endif

<div x-data="backToTop">
    <button
        type="button"
        x-show="showTop"
        x-cloak
        x-transition.opacity
        x-on:click="window.scrollTo({ top: 0, behavior: 'smooth' })"
        aria-label="{{ __('shop.to_top') }}"
        @class([
            'fixed z-45 grid size-12 place-items-center rounded-full bg-navy text-white shadow-[0_12px_28px_-8px_rgba(10,22,51,.5)] hover:bg-brand',
            'right-6 bottom-[calc(84px+env(safe-area-inset-bottom))] sm:bottom-6' => ! $messenger,
            'right-7 bottom-[calc(152px+env(safe-area-inset-bottom))] sm:bottom-[92px]' => $messenger,
        ])
    >
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m6 14 6-6 6 6"/></svg>
    </button>
</div>

<div x-data
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
