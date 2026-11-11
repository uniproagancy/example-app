@if (request()->is('admin', 'admin/*'))
    @include('errors.partials.standalone-404')
@else
@php
    $errorRoots = App\Support\CategoryTree::roots()->take(8);
@endphp

<x-layouts::app :title="__('errors.404.title')">
    <main class="mx-auto flex max-w-[1480px] flex-col gap-10 px-4 pt-6 sm:px-6 sm:gap-14 lg:px-8">
        <section class="relative grid grid-cols-1 items-center gap-6 overflow-hidden rounded-[28px] bg-navy px-6 py-10 text-white sm:px-[52px] sm:py-14 lg:grid-cols-[minmax(0,1fr)_auto] lg:gap-12">
            <div class="pointer-events-none absolute -top-[180px] -right-[140px] size-[520px] rounded-full bg-[radial-gradient(circle,rgba(20,180,255,.3),transparent_65%)]"></div>
            <div class="pointer-events-none absolute -bottom-[220px] left-[20%] size-[420px] rounded-full bg-[radial-gradient(circle,rgba(11,99,246,.35),transparent_65%)]"></div>

            <div class="relative flex flex-col gap-3.5">
                <span class="text-xs font-bold tracking-[0.8px] text-sky uppercase">{{ __('errors.label', ['code' => 404]) }}</span>
                <h1 class="m-0 font-sans text-[clamp(28px,3.4vw,44px)] leading-[1.15] font-extrabold normal-case">{{ __('errors.404.title') }}</h1>
                <p class="m-0 max-w-[520px] text-[15px] leading-relaxed text-faint">{{ __('errors.404.text') }}</p>
                <div class="mt-3 flex flex-wrap gap-2.5">
                    <a href="{{ route('home') }}" wire:navigate class="flex h-12 items-center gap-2 rounded-full bg-white px-6 text-sm font-bold text-navy hover:bg-brand hover:text-white">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11 12 4l9 7"/><path d="M5 10v10h14V10"/></svg>
                        {{ __('errors.home') }}
                    </a>
                    <a href="{{ route('catalog') }}" wire:navigate class="flex h-12 items-center gap-2 rounded-full border-[1.5px] border-white/25 px-6 text-sm font-bold text-white hover:border-white hover:text-white">
                        {{ __('errors.catalog') }}
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                </div>
                <span class="mt-1 text-[13px] text-foot">{{ __('errors.search_hint') }}</span>
            </div>

            <span class="relative order-first bg-[linear-gradient(120deg,#FFFFFF_15%,#14B4FF_70%)] bg-clip-text font-display text-[104px] leading-none font-extrabold tracking-[-6px] text-transparent select-none sm:text-[150px] lg:order-none lg:text-[200px]" aria-hidden="true">404</span>
        </section>

        @if ($errorRoots->isNotEmpty())
            <section class="flex flex-col gap-5">
                <h2 class="m-0 text-xl font-extrabold tracking-[0.3px] sm:text-2xl">{{ __('errors.categories') }}</h2>
                <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4 sm:gap-3.5">
                    @foreach ($errorRoots as $root)
                        <a href="{{ App\Support\CategoryTree::url($root) }}" wire:navigate class="group flex items-center gap-3 rounded-[18px] border border-line-mid bg-white p-3.5 text-navy transition-colors hover:border-brand hover:text-navy sm:p-4">
                            <span class="grid size-11 shrink-0 place-items-center rounded-[14px] bg-tint text-brand transition-colors group-hover:bg-brand group-hover:text-white">
                                @include('partials.category-icon', ['icon' => $root['icon'], 'stroke' => 1.8, 'class' => 'size-[22px]'])
                            </span>
                            <span class="line-clamp-2 text-[13px] leading-[1.35] font-bold sm:text-sm">{{ $root['short'] ?: $root['title'] }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </main>
</x-layouts::app>
@endif
