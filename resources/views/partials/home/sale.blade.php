        <section x-data="carousel" class="mt-6 rounded-[20px] bg-navy px-4 pt-6 pb-5 text-white sm:mt-10 sm:rounded-[28px] sm:px-8 sm:pt-9 sm:pb-8">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-5">
                <div class="flex items-center gap-4">
                    <span class="grid size-12 place-items-center rounded-[14px] bg-sale font-display text-xl font-extrabold">%</span>
                    <div class="flex flex-col gap-1">
                        <h2 class="m-0 text-xl font-extrabold tracking-[0.3px] text-white sm:text-[26px]">{{ __('shop.sale.title') }}</h2>
                        <span class="text-[13px] text-faint">{{ __('shop.sale.subtitle') }}</span>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3.5">
                    <div x-data="countdown" class="flex items-center gap-1.5">
                        <span class="mr-1 text-xs text-faint">{{ __('shop.sale.ends_in') }}</span>
                        @foreach (['hours', 'minutes', 'seconds'] as $unit)
                            <div class="min-w-[46px] rounded-[10px] bg-white/8 p-1.5 text-center leading-[1.15]">
                                <div class="font-display text-[15px] font-extrabold text-white tabular-nums" x-text="{{ $unit }}">00</div>
                                <div class="text-[10px] text-faint">{{ __("shop.timer.$unit") }}</div>
                            </div>
                        @endforeach
                    </div>
                    <div class="hidden gap-1.5 sm:flex">
                        <button type="button" x-on:click="scroll(-1)" aria-label="{{ __('shop.prev') }}" class="grid size-10 place-items-center rounded-full bg-white/10 text-white hover:bg-brand">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 6-6 6 6 6"/></svg>
                        </button>
                        <button type="button" x-on:click="scroll(1)" aria-label="{{ __('shop.next') }}" class="grid size-10 place-items-center rounded-full bg-white/10 text-white hover:bg-brand">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
                        </button>
                    </div>
                    <a href="{{ route('catalog', ['only' => ['sale']]) }}" wire:navigate class="flex h-10 items-center rounded-full bg-white px-[18px] text-[13px] font-bold text-navy hover:bg-brand hover:text-white">{{ __('shop.sale.all') }}</a>
                </div>
            </div>
            <div x-ref="track" class="carousel auto-cols-[calc((100%-10px)/2)] gap-2.5 sm:auto-cols-[calc((100%-24px)/3)] sm:gap-3 md:auto-cols-[calc((100%-36px)/4)] lg:auto-cols-[calc((100%-48px)/5)] xl:auto-cols-[calc((100%-60px)/6)]">
                @foreach ($saleProducts as $product)
                    @include('partials.product-card', ['product' => $product])
                @endforeach
            </div>
        </section>
