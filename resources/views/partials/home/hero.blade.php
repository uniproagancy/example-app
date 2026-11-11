@php
    $heroBackgrounds = [
        'blue' => 'radial-gradient(circle at 78% 40%, rgba(20,180,255,.45), transparent 42%), radial-gradient(circle at 95% 95%, rgba(11,99,246,.55), transparent 45%), #0A1633',
        'red' => 'radial-gradient(circle at 75% 35%, rgba(229,72,77,.45), transparent 42%), radial-gradient(circle at 92% 90%, rgba(11,99,246,.5), transparent 45%), #0A1633',
        'indigo' => 'radial-gradient(circle at 80% 45%, rgba(42,123,255,.55), transparent 45%), radial-gradient(circle at 60% 100%, rgba(20,180,255,.35), transparent 40%), #0A1633',
    ];
@endphp

    @if ($slides->isNotEmpty())
    <section class="mx-auto max-w-[1480px] px-4 pt-4 sm:px-6 sm:pt-6 lg:px-8" x-data="heroSlider({{ $slides->count() }})">
        <div class="relative h-[400px] overflow-hidden rounded-[20px] bg-navy sm:h-[clamp(320px,44vh,460px)] sm:rounded-3xl">
            @foreach ($slides as $index => $slide)
                <div
                    wire:key="hero-slide-bg-{{ $slide['id'] }}"
                    x-show="slide === {{ $index }}"
                    x-transition.opacity.duration.500ms
                    @if ($index > 0) x-cloak @endif
                    class="absolute inset-0"
                    style="background: {{ $heroBackgrounds[$slide['theme']] }}"
                >
                    @if ($slide['image'])
                        <img src="{{ $slide['image'] }}" alt="" class="absolute inset-0 size-full object-cover" @if ($index > 0) loading="lazy" @endif>
                    @elseif ($slide['icon'])
                        @include('partials.category-icon', ['icon' => $slide['icon'], 'class' => 'absolute top-1/2 right-[8%] hidden h-[78%] -translate-y-1/2 text-white/10 sm:block', 'stroke' => 0.6])
                    @endif
                </div>
            @endforeach

            <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(0deg,rgba(10,22,51,.92)_0%,rgba(10,22,51,.55)_45%,rgba(10,22,51,0)_75%)] sm:bg-[linear-gradient(90deg,rgba(10,22,51,.82)_0%,rgba(10,22,51,.45)_40%,rgba(10,22,51,0)_70%)]"></div>

            @foreach ($slides as $index => $slide)
                <div
                    x-show="slide === {{ $index }}"
                    @if ($index > 0) x-cloak @endif
                    class="pointer-events-none absolute inset-0 flex items-end px-5 pb-6 sm:items-center sm:px-16 sm:pb-0 lg:px-[88px]"
                >
                    <div class="flex max-w-[480px] animate-slide-in flex-col gap-3 text-white">
                        <span class="h-1.5 w-9 self-start rounded-[3px] bg-white/55"></span>
                        <{{ $index === 0 ? 'h1' : 'h2' }} class="m-0 text-[clamp(22px,2.4vw,32px)] leading-tight font-extrabold tracking-[0.2px] text-balance uppercase">{{ $slide['title'] }}</{{ $index === 0 ? 'h1' : 'h2' }}>
                        @if ($slide['text'])
                            <p class="m-0 max-w-[400px] text-sm leading-relaxed text-pretty text-[#D3DDF0]">{{ $slide['text'] }}</p>
                        @endif
                        @if ($slide['href'])
                            <div class="pointer-events-auto mt-1.5 flex flex-wrap gap-2.5">
                                <a href="{{ $slide['href'] }}" wire:navigate class="flex h-[42px] items-center rounded-full bg-white px-[22px] text-[13px] font-bold whitespace-nowrap text-navy hover:bg-brand hover:text-white">{{ $slide['buttonLabel'] ?: __('shop.details') }}</a>
                                @if ($slide['buttonLabel'])
                                    <a href="{{ $slide['href'] }}" wire:navigate class="flex h-[42px] items-center rounded-full border-[1.5px] border-white/45 px-5 text-[13px] font-bold whitespace-nowrap text-white hover:border-white hover:text-white">{{ __('shop.details') }}</a>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach

            @if ($slides->count() > 1)
                <button type="button" x-on:click="go(slide - 1)" aria-label="{{ __('shop.prev') }}" class="absolute top-1/2 left-4 hidden size-10 -translate-y-1/2 place-items-center rounded-full border border-white/25 bg-white/14 text-white backdrop-blur-md hover:bg-white hover:text-navy sm:grid">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 6-6 6 6 6"/></svg>
                </button>
                <button type="button" x-on:click="go(slide + 1)" aria-label="{{ __('shop.next') }}" class="absolute top-1/2 right-4 hidden size-10 -translate-y-1/2 place-items-center rounded-full border border-white/25 bg-white/14 text-white backdrop-blur-md hover:bg-white hover:text-navy sm:grid">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
                </button>
                <span class="pointer-events-none absolute top-6 right-7 font-display text-[13px] font-extrabold tracking-[0.6px] text-white" x-text="`${String(slide + 1).padStart(2, '0')} / ${String(count).padStart(2, '0')}`">01 / {{ str_pad((string) $slides->count(), 2, '0', STR_PAD_LEFT) }}</span>
            @endif
        </div>

        @if ($slides->count() > 1)
        <div class="mt-3 grid gap-2 sm:mt-4 sm:gap-6" style="grid-template-columns: repeat({{ min($slides->count(), 6) }}, minmax(0, 1fr))">
            @foreach ($slides as $index => $slide)
                <button type="button" x-on:click="go({{ $index }})" class="flex flex-col gap-2.5 border-0 bg-transparent p-0 text-left transition-colors hover:text-navy" :class="slide === {{ $index }} ? 'text-navy' : 'text-subtle'">
                    <span class="relative block h-[3px] w-full overflow-hidden rounded-sm bg-line">
                        <template x-if="slide === {{ $index }}">
                            <span class="absolute inset-y-0 left-0 animate-progress rounded-sm bg-brand"></span>
                        </template>
                    </span>
                    <span class="flex items-baseline gap-2.5">
                        <span class="font-display text-xs font-extrabold">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="hidden text-[13px] leading-[1.4] font-bold sm:inline">{{ $slide['title'] }}</span>
                    </span>
                </button>
            @endforeach
        </div>
        @endif
    </section>
    @endif
