@php
    $tileBackgrounds = ['#E9F1FF', '#F3F5F9', '#F3F5F9', '#EEF6F2', '#F3F5F9', '#F6F1FA'];
@endphp

        <section class="mt-11 sm:mt-[72px]">
            <div class="mb-[22px] flex items-end justify-between gap-4">
                <div class="flex flex-col gap-1.5">
                    <span class="font-display text-xs font-extrabold tracking-[1px] text-brand">02</span>
                    <h2 class="m-0 text-xl font-extrabold tracking-[0.3px] sm:text-[28px]">{{ __('shop.categories_title') }}</h2>
                </div>
                <a href="#" class="text-sm font-bold">{{ __('shop.all_products') }}</a>
            </div>
            <div class="grid auto-rows-[140px] grid-cols-2 gap-2.5 sm:auto-rows-[170px] sm:grid-cols-4 sm:gap-3.5 lg:auto-rows-[190px] lg:grid-cols-5">
                @foreach ($tiles as $index => $tile)
                    <a
                        href="{{ $tile['url'] }}"
                        wire:navigate
                        @class([
                            'relative overflow-hidden rounded-3xl text-navy transition duration-200 hover:-translate-y-[3px] hover:text-navy hover:shadow-[0_22px_44px_-22px_rgba(10,22,51,.35)]',
                            'col-span-2 lg:row-span-2' => $index === 0,
                        ])
                        style="background: {{ $tileBackgrounds[$index] }}"
                    >
                        @include('partials.category-icon', ['icon' => $tile['icon'], 'class' => 'absolute right-[6%] bottom-[6%] text-brand/25 '.($index === 0 ? 'w-[46%] lg:w-[58%]' : 'w-[46%]')])
                        <div class="pointer-events-none absolute top-[18px] right-5 left-5 flex flex-col gap-1">
                            <span @class([
                                'font-caps leading-[1.3] font-extrabold tracking-[0.3px] uppercase',
                                'text-base sm:text-xl' => $index === 0,
                                'text-[13px]' => $index !== 0,
                            ])>{{ $tile['short'] }}</span>
                            <span class="font-display text-xs font-bold text-muted">{{ $tile['count'] ? __('shop.products_count', ['count' => $tile['count']]) : __('shop.categories_count', ['count' => count($tile['groups'])]) }}</span>
                        </div>
                    </a>
                @endforeach
                <button type="button" x-on:click="$dispatch('open-catalog')" class="relative flex flex-col justify-between rounded-3xl bg-navy px-5 py-[18px] text-left text-white transition-colors hover:bg-brand">
                    <span class="font-caps text-sm leading-[1.35] font-extrabold tracking-[0.3px] uppercase">{{ __('shop.all_categories') }}</span>
                    <span class="grid size-[42px] place-items-center self-start rounded-full bg-white text-navy">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </span>
                </button>
            </div>
        </section>
