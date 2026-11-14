<?php

use App\Support\Catalog;
use App\Support\HomeContent;
use App\Support\Seo;
use App\Support\Site;
use Illuminate\Support\Carbon;
use Livewire\Component;

new class extends Component
{
    public function render()
    {
        $saleProducts = Catalog::onSale();

        Seo::set([
            'description' => Seo::text(__('seo.promotions_description', ['site' => Site::name()])),
            'canonical' => route('promotions'),
            'schema' => [Seo::breadcrumbs([['name' => __('pages.promotions.title'), 'url' => route('promotions')]])],
        ]);

        return $this->view([
            'promos' => HomeContent::promos(),
            'deals' => Catalog::deals(),
            'saleProducts' => $saleProducts->sortByDesc(fn (array $product) => $product['old'] - ($product['price'] ?? $product['old']))->take(15)->values(),
            'saleCount' => $saleProducts->count(),
        ])->title(__('pages.promotions.title'));
    }

    public function validity(?string $endsAt): string
    {
        if (! $endsAt) {
            return __('pages.promotions.no_end');
        }

        $date = Carbon::parse($endsAt)->timezone(config('app.timezone'));

        return $date->isToday()
            ? __('pages.promotions.ends_today')
            : __('pages.promotions.until', ['date' => $date->format('d.m.Y')]);
    }
};
?>

@php
    $promoThemes = [
        'navy' => ['bg' => 'bg-navy', 'fg' => 'text-white', 'muted' => 'text-faint', 'accent' => 'text-sky', 'badge' => 'text-sale', 'icon' => 'text-white/12', 'chip' => 'bg-white/10 text-white'],
        'tint' => ['bg' => 'bg-tint', 'fg' => 'text-navy', 'muted' => 'text-[#4A5A78]', 'accent' => 'text-brand', 'badge' => 'text-brand', 'icon' => 'text-brand/12', 'chip' => 'bg-white text-navy'],
        'field' => ['bg' => 'bg-field', 'fg' => 'text-navy', 'muted' => 'text-[#4A5A78]', 'accent' => 'text-brand', 'badge' => 'text-brand', 'icon' => 'text-brand/12', 'chip' => 'bg-white text-navy'],
    ];
    $clockIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
    $arrowIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>';
@endphp

<main class="mx-auto flex max-w-[1480px] flex-col gap-6 px-4 pt-6 sm:px-6 lg:px-8">
    <nav aria-label="breadcrumb" class="flex items-center gap-2 text-[13px] text-muted">
        <a href="{{ route('home') }}" wire:navigate class="text-muted hover:text-brand">{{ __('account.home') }}</a>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
        <span class="font-semibold text-navy">{{ __('pages.promotions.title') }}</span>
    </nav>

    <section class="relative overflow-hidden rounded-[28px] bg-navy px-6 py-9 text-white sm:px-12 sm:py-12">
        <div class="pointer-events-none absolute -top-[180px] -right-[120px] size-[460px] rounded-full bg-[radial-gradient(circle,rgba(20,180,255,.28),transparent_65%)]"></div>
        <div class="relative flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="flex max-w-[640px] flex-col gap-3">
                <span class="flex items-center gap-2 text-xs font-bold tracking-[0.8px] text-sky uppercase">
                    <span class="grid size-7 place-items-center rounded-lg bg-sale font-display text-sm font-extrabold text-white">%</span>
                    {{ __('pages.promotions.title') }}
                </span>
                <h1 class="m-0 font-sans text-[clamp(26px,3.2vw,40px)] leading-[1.18] font-extrabold normal-case">{{ __('pages.promotions.heading') }}</h1>
                <p class="m-0 text-[15px] leading-relaxed text-faint">{{ __('pages.promotions.text') }}</p>
            </div>
            <div class="flex flex-wrap gap-2.5">
                @foreach (array_filter([
                    'campaigns' => $promos->count(),
                    'deals' => $deals->count(),
                    'sale' => $saleCount,
                ]) as $key => $count)
                    <a href="#promotions-{{ $key }}" class="flex flex-col rounded-2xl bg-white/8 px-5 py-3 leading-tight hover:bg-white/12 hover:text-white">
                        <span class="font-display text-2xl font-extrabold text-white tabular-nums">{{ $count }}</span>
                        <span class="text-xs text-faint">{{ __("pages.promotions.$key") }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    @if ($promos->isEmpty() && $deals->isEmpty() && $saleCount === 0)
        <section class="flex flex-col items-center gap-3 rounded-[28px] border border-line-soft bg-white px-6 py-16 text-center">
            <span class="grid size-16 place-items-center rounded-2xl bg-soft font-display text-3xl font-extrabold text-brand">%</span>
            <h2 class="m-0 text-xl font-extrabold">{{ __('pages.promotions.empty') }}</h2>
            <p class="m-0 max-w-[420px] text-sm text-muted">{{ __('pages.promotions.empty_text') }}</p>
            <a href="{{ route('catalog') }}" wire:navigate class="mt-2 flex h-12 items-center rounded-full bg-brand px-6 text-sm font-bold text-white hover:bg-brand-dark hover:text-white">{{ __('pages.promotions.catalog') }}</a>
        </section>
    @endif

    @if ($promos->isNotEmpty())
        <section id="promotions-campaigns" class="mt-4 flex scroll-mt-28 flex-col gap-5">
            <h2 class="m-0 text-2xl font-extrabold tracking-[0.3px]">{{ __('pages.promotions.campaigns') }}</h2>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($promos as $promo)
                    @php($theme = $promoThemes[$promo['theme']])
                    <article wire:key="promo-{{ $loop->index }}" class="relative flex min-h-[260px] flex-col overflow-hidden rounded-3xl p-6 sm:p-8 {{ $theme['bg'] }}">
                        @if ($promo['icon'])
                            @include('partials.category-icon', ['icon' => $promo['icon'], 'stroke' => 0.8, 'class' => 'pointer-events-none absolute -right-6 -bottom-6 h-[60%] '.$theme['icon']])
                        @endif
                        <div class="relative flex flex-1 flex-col gap-2.5">
                            <span class="inline-flex items-center gap-1.5 self-start rounded-full px-3 py-1 text-xs font-semibold {{ $theme['chip'] }}">{!! $clockIcon !!} {{ $this->validity($promo['endsAt']) }}</span>
                            @if ($promo['badge'])
                                <span class="mt-2 font-display text-[44px] leading-none font-extrabold {{ $theme['badge'] }}">{{ $promo['badge'] }}</span>
                            @endif
                            <h3 class="m-0 font-caps text-xl font-extrabold uppercase {{ $theme['fg'] }}">{{ $promo['title'] }}</h3>
                            @if ($promo['text'])
                                <p class="m-0 text-sm leading-relaxed {{ $theme['muted'] }}">{{ $promo['text'] }}</p>
                            @endif
                            @if ($promo['note'])
                                <p class="m-0 text-[13px] font-semibold {{ $theme['accent'] }}">{{ $promo['note'] }}</p>
                            @endif
                        </div>
                        @if ($promo['href'])
                            <a href="{{ $promo['href'] }}" wire:navigate class="relative mt-5 flex h-11 items-center gap-1.5 self-start rounded-full bg-brand px-5 text-sm font-bold text-white hover:bg-brand-dark hover:text-white">
                                {{ __('pages.promotions.more') }} {!! $arrowIcon !!}
                            </a>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if ($deals->isNotEmpty())
        <section id="promotions-deals" class="mt-6 flex scroll-mt-28 flex-col gap-5">
            <h2 class="m-0 text-2xl font-extrabold tracking-[0.3px]">{{ __('pages.promotions.deals') }}</h2>
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                @foreach ($deals as $deal)
                    <article wire:key="deal-{{ $deal['id'] }}" class="grid grid-cols-[120px_1fr] gap-4 overflow-hidden rounded-3xl border border-line-soft bg-white p-4 sm:grid-cols-[180px_1fr] sm:gap-6 sm:p-5">
                        <a href="{{ $deal['url'] }}" wire:navigate class="relative grid aspect-square place-items-center rounded-2xl bg-card">
                            @if ($deal['image'])
                                <img src="{{ $deal['image'] }}" alt="{{ $deal['name'] }}" loading="lazy" class="size-full object-contain p-3">
                            @else
                                @include('partials.category-icon', ['icon' => $deal['cat'], 'stroke' => 0.8, 'class' => 'h-[60%] text-brand/20'])
                            @endif
                        </a>
                        <div class="flex min-w-0 flex-col justify-center gap-2">
                            <span class="inline-flex items-center gap-1.5 self-start rounded-full bg-sale/10 px-3 py-1 text-xs font-semibold text-sale">{!! $clockIcon !!} {{ $this->validity($deal['deal']['endsAt']) }}</span>
                            <a href="{{ $deal['url'] }}" wire:navigate class="line-clamp-2 text-base font-bold text-navy hover:text-brand">{{ $deal['name'] }}</a>
                            @if ($deal['deal']['text'])
                                <p class="m-0 line-clamp-2 text-[13px] text-muted">{{ $deal['deal']['text'] }}</p>
                            @endif
                            <div class="flex flex-wrap items-baseline gap-2.5">
                                <span class="font-display text-2xl font-extrabold text-navy">{{ $deal['priceLabel'] }}</span>
                                @if ($deal['oldLabel'])
                                    <span class="text-sm text-subtle line-through">{{ $deal['oldLabel'] }}</span>
                                @endif
                            </div>
                            <div class="mt-1 flex flex-wrap gap-2">
                                @if ($deal['inStock'])
                                    <button type="button" x-on:click="Livewire.dispatch('cart-add', { id: {{ $deal['id'] }} })" class="h-10 rounded-xl bg-brand px-4 text-[13px] font-bold text-white hover:bg-brand-bright">{{ __('shop.add_to_cart_full') }}</button>
                                @endif
                                <a href="{{ $deal['url'] }}" wire:navigate class="flex h-10 items-center rounded-xl bg-soft px-4 text-[13px] font-bold text-navy hover:bg-brand hover:text-white">{{ __('pages.promotions.buy') }}</a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if ($saleProducts->isNotEmpty())
        <section id="promotions-sale" class="mt-6 flex scroll-mt-28 flex-col gap-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <h2 class="m-0 text-2xl font-extrabold tracking-[0.3px]">{{ __('pages.promotions.sale') }}</h2>
                    <span class="text-[13px] text-muted">{{ __('pages.promotions.sale_count', ['count' => $saleCount]) }}</span>
                </div>
                <a href="{{ route('catalog', ['only' => ['sale']]) }}" wire:navigate class="flex h-10 items-center gap-1.5 rounded-full bg-navy px-[18px] text-[13px] font-bold text-white hover:bg-brand hover:text-white">
                    {{ __('pages.promotions.all_sale') }} {!! $arrowIcon !!}
                </a>
            </div>
            <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4 xl:grid-cols-5">
                @foreach ($saleProducts as $item)
                    <div wire:key="sale-{{ $item['id'] }}">
                        @include('partials.product-card', ['product' => $item])
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</main>
