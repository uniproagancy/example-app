@if ($recentProducts->isNotEmpty())
    <section x-data="carousel" class="mt-14 lg:mt-[72px]">
        <div class="mb-5 flex items-center justify-between gap-4">
            <h2 class="m-0 text-2xl font-extrabold tracking-[0.3px]">{{ __('product.recently_viewed') }}</h2>
            <div class="flex gap-1.5">
                <button type="button" x-on:click="scroll(-1)" aria-label="{{ __('shop.prev') }}" class="grid size-10 place-items-center rounded-full bg-soft text-navy hover:bg-brand hover:text-white">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 6-6 6 6 6"/></svg>
                </button>
                <button type="button" x-on:click="scroll(1)" aria-label="{{ __('shop.next') }}" class="grid size-10 place-items-center rounded-full bg-soft text-navy hover:bg-brand hover:text-white">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
                </button>
            </div>
        </div>
        <div x-ref="track" class="carousel auto-cols-[calc((100%-10px)/2)] gap-2.5 sm:auto-cols-[calc((100%-40px)/3)] sm:gap-5 lg:auto-cols-[calc((100%-60px)/4)] xl:auto-cols-[calc((100%-80px)/5)]">
            @foreach ($recentProducts as $item)
                <div wire:key="recent-{{ $item['id'] }}">
                    @include('partials.product-card', ['product' => $item])
                </div>
            @endforeach
        </div>
    </section>
@endif
