<?php

use App\Support\Catalog;
use App\Support\CategoryTree;
use App\Support\Seo;
use App\Support\Site;
use Livewire\Component;

new class extends Component
{
    public function render()
    {
        $products = Catalog::products();

        Seo::set(['description' => Seo::text(__('seo.about_description', ['site' => Site::name()]))]);

        return $this->view([
            'siteName' => Site::name(),
            'stats' => [
                'products' => $products->count(),
                'brands' => $products->pluck('brand')->filter()->unique()->count(),
                'categories' => CategoryTree::nodes(app()->getLocale())->count(),
            ],
            'firstCategory' => CategoryTree::roots()->first(),
        ])->title(__('pages.about.title'));
    }
};
?>

@php
    $featureIcons = [
        'warranty' => '<path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6zM8.5 12l2.5 2.5 4.5-5"/>',
        'delivery' => '<path d="M3 6h11v10H3zM14 10h4l3 3v3h-7M7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM17 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/>',
        'installments' => '<path d="M3 6h18v12H3zM3 10h18M7 15h4"/>',
        'returns' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>',
    ];
@endphp

<main class="mx-auto flex max-w-[1480px] flex-col gap-6 px-4 pt-6 sm:px-6 lg:px-8">
    <nav aria-label="breadcrumb" class="flex items-center gap-2 text-[13px] text-muted">
        <a href="{{ route('home') }}" wire:navigate class="text-muted hover:text-brand">{{ __('account.home') }}</a>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
        <span class="font-semibold text-navy">{{ __('pages.about.title') }}</span>
    </nav>

    <section class="relative grid min-h-[440px] grid-cols-1 overflow-hidden rounded-[28px] bg-navy text-white lg:grid-cols-2">
        <div class="pointer-events-none absolute -bottom-[200px] -left-[140px] size-[480px] rounded-full bg-[radial-gradient(circle,rgba(20,180,255,.25),transparent_65%)]"></div>
        <div class="relative flex flex-col justify-center gap-[18px] px-6 py-8 sm:px-[52px] sm:py-14">
            <span class="text-xs font-bold tracking-[0.8px] text-sky uppercase">{{ __('pages.about.title') }}</span>
            <h1 class="m-0 font-sans text-[clamp(28px,3.4vw,44px)] leading-[1.18] font-extrabold normal-case">{{ __('pages.about.heading') }}</h1>
            <p class="m-0 max-w-[520px] text-base leading-[1.65] text-faint">{{ __('pages.about.text', ['name' => $siteName]) }}</p>
            <div class="mt-1.5 flex flex-wrap gap-2.5">
                @if ($firstCategory)
                    <a href="{{ CategoryTree::url($firstCategory) }}" wire:navigate class="flex h-[50px] items-center rounded-full bg-brand px-6 text-sm font-extrabold text-white hover:bg-brand-dark hover:text-white">{{ __('pages.about.catalog') }}</a>
                @endif
                <a href="{{ route('contact') }}" wire:navigate class="flex h-[50px] items-center rounded-full border-[1.5px] border-white/25 px-6 text-sm font-bold text-white hover:bg-white/8 hover:text-white">{{ __('pages.about.contact') }}</a>
            </div>
        </div>
        <div class="relative grid grid-cols-3 content-center gap-3 px-6 pb-8 lg:grid-cols-1 lg:px-[52px] lg:py-14">
            @foreach ($stats as $key => $value)
                <div class="flex flex-col gap-1 rounded-[20px] border border-white/10 bg-white/7 px-4 py-4 sm:px-6 sm:py-5">
                    <span class="font-display text-[28px] leading-none font-extrabold sm:text-[40px]">{{ $value }}</span>
                    <span class="text-xs text-faint sm:text-sm">{{ __("pages.about.stats.$key") }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <section class="grid grid-cols-1 items-start gap-x-10 gap-y-6 px-2 py-8 md:grid-cols-2">
        <h2 class="m-0 font-sans text-[26px] leading-tight font-extrabold normal-case sm:text-[30px]">{{ __('pages.about.story_title', ['name' => $siteName]) }}</h2>
        <div class="flex flex-col gap-3.5 text-base leading-[1.7] text-[#3A4A68]">
            @foreach (__('pages.about.story') as $paragraph)
                <p class="m-0">{{ $paragraph }}</p>
            @endforeach
        </div>
    </section>

    <section class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        @foreach (__('pages.about.features') as $key => $feature)
            <div class="flex flex-col gap-3.5 rounded-3xl border border-line bg-white p-6">
                <span class="grid size-12 shrink-0 place-items-center rounded-[14px] bg-[#EAF2FF] text-brand">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $featureIcons[$key] !!}</svg>
                </span>
                <span class="flex flex-col gap-1.5">
                    <span class="text-[17px] font-extrabold">{{ $feature['title'] }}</span>
                    <span class="text-sm leading-[1.55] text-muted">{{ $feature['text'] }}</span>
                </span>
            </div>
        @endforeach
    </section>
</main>
