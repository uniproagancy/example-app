        <section id="home-campaigns" class="mt-11 scroll-mt-[140px] sm:mt-[72px]">
            <div class="mb-[22px] flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="grid size-12 place-items-center rounded-[14px] bg-sale font-display text-xl font-extrabold text-white">%</span>
                    <div class="flex flex-col gap-1">
                        <h2 class="m-0 text-xl font-extrabold tracking-[0.3px] sm:text-[28px]">{{ __('shop.sale.title') }}</h2>
                        <span class="text-[13px] text-muted">{{ __('shop.sale.subtitle') }}</span>
                    </div>
                </div>
                <a href="{{ route('promotions') }}" wire:navigate class="flex h-10 items-center rounded-full bg-navy px-[18px] text-[13px] font-bold text-white hover:bg-brand hover:text-white">{{ __('shop.sale.all') }}</a>
            </div>
            <div class="flex flex-col gap-6 sm:gap-8">
                @foreach ($campaigns as $campaign)
                    <div wire:key="home-campaign-{{ $campaign['id'] }}" x-data="carousel" class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,300px)_minmax(0,1fr)] lg:gap-5">
                        @include('partials.campaign-card', ['campaign' => $campaign])
                        @if ($campaign['products']->isNotEmpty())
                            <div class="relative min-w-0">
                                <div x-ref="track" class="carousel h-full auto-cols-[calc((100%-10px)/2)] gap-2.5 sm:auto-cols-[calc((100%-24px)/3)] sm:gap-3 xl:auto-cols-[calc((100%-36px)/4)] 2xl:auto-cols-[calc((100%-48px)/5)]">
                                    @foreach ($campaign['products'] as $product)
                                        @include('partials.product-card', ['product' => $product])
                                    @endforeach
                                </div>
                                @if ($campaign['products']->count() > 2)
                                    <button type="button" x-on:click="scroll(-1)" aria-label="{{ __('shop.prev') }}" class="absolute top-1/2 -left-3 hidden size-10 -translate-y-1/2 place-items-center rounded-full bg-white text-navy shadow-[0_4px_14px_rgba(10,22,51,.18)] hover:bg-brand hover:text-white sm:grid">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 6-6 6 6 6"/></svg>
                                    </button>
                                    <button type="button" x-on:click="scroll(1)" aria-label="{{ __('shop.next') }}" class="absolute top-1/2 -right-3 hidden size-10 -translate-y-1/2 place-items-center rounded-full bg-white text-navy shadow-[0_4px_14px_rgba(10,22,51,.18)] hover:bg-brand hover:text-white sm:grid">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
