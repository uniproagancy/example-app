@if ($recentProducts->isNotEmpty())
    <section
        x-data="carousel"
        class="mt-14 bg-[#EEF4FF] py-9 shadow-[0_0_0_100vmax_#EEF4FF] [clip-path:inset(0_-100vmax)] sm:py-11 lg:mt-[72px]"
    >
        <div class="mb-5 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-full bg-white text-brand shadow-[0_6px_16px_-10px_rgba(11,99,246,.6)]">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                </span>
                <h2 class="m-0 text-xl font-extrabold tracking-[0.3px] sm:text-2xl">{{ __('product.recently_viewed') }}</h2>
            </div>
            <div class="flex gap-1.5">
                <button type="button" x-on:click="scroll(-1)" aria-label="{{ __('shop.prev') }}" class="grid size-10 place-items-center rounded-full bg-white text-navy shadow-[0_1px_2px_rgba(10,22,51,.06)] hover:bg-brand hover:text-white">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 6-6 6 6 6"/></svg>
                </button>
                <button type="button" x-on:click="scroll(1)" aria-label="{{ __('shop.next') }}" class="grid size-10 place-items-center rounded-full bg-white text-navy shadow-[0_1px_2px_rgba(10,22,51,.06)] hover:bg-brand hover:text-white">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
                </button>
            </div>
        </div>
        <div x-ref="track" class="carousel auto-cols-[86%] gap-3 sm:auto-cols-[calc((100%-16px)/2)] sm:gap-4 lg:auto-cols-[calc((100%-32px)/3)] xl:auto-cols-[calc((100%-48px)/4)]">
            @foreach ($recentProducts as $item)
                <div wire:key="recent-{{ $item['id'] }}">
                    @include('partials.recent-card', ['product' => $item])
                </div>
            @endforeach
        </div>
    </section>
@endif
