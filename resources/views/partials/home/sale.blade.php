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
                    <div wire:key="home-campaign-{{ $campaign['id'] }}" x-data="carousel" class="relative isolate grid grid-cols-1 gap-5 overflow-hidden rounded-3xl p-5 text-white sm:p-7 lg:grid-cols-[minmax(0,260px)_minmax(0,1fr)] lg:gap-7 {{ $campaign['background'] }}">
                        @if ($campaign['image'])
                            <img src="{{ $campaign['image'] }}" alt="" loading="lazy" class="absolute inset-0 -z-10 size-full object-cover">
                            <div class="absolute inset-0 -z-10 bg-[linear-gradient(90deg,rgba(10,22,51,.92)_0%,rgba(10,22,51,.75)_100%)]"></div>
                        @else
                            <span class="pointer-events-none absolute -right-4 -bottom-10 -z-10 font-display text-[200px] leading-none font-extrabold text-white/6 select-none">%</span>
                        @endif

                        <div class="flex flex-col gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex h-7 items-center gap-1.5 rounded-full bg-white/12 px-3 text-xs font-semibold backdrop-blur-sm">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                    {{ App\Models\Campaign::validity($campaign['endsAt']) }}
                                </span>
                                @if ($campaign['maxDiscount'] > 0)
                                    <span class="inline-flex h-7 items-center rounded-full bg-sale px-3 text-xs font-extrabold">{{ __('pages.campaign.up_to', ['percent' => $campaign['maxDiscount']]) }}</span>
                                @endif
                            </div>
                            <div class="mt-auto flex flex-col gap-2 pt-4 lg:pt-10">
                                @if ($campaign['badge'])
                                    <span class="font-display text-[44px] leading-none font-extrabold">{{ $campaign['badge'] }}</span>
                                @endif
                                <h3 class="m-0 font-caps text-xl leading-tight font-extrabold uppercase">
                                    <a href="{{ $campaign['url'] }}" wire:navigate class="text-white hover:text-white/80">{{ $campaign['title'] }}</a>
                                </h3>
                                @if ($campaign['text'])
                                    <p class="m-0 line-clamp-3 text-sm leading-relaxed text-white/75">{{ $campaign['text'] }}</p>
                                @endif
                                <span class="mt-3 flex flex-wrap items-center justify-between gap-3">
                                    <span class="text-[13px] font-semibold text-white/75">{{ trans_choice('pages.campaign.products', $campaign['count'], ['count' => $campaign['count']]) }}</span>
                                    <a href="{{ $campaign['url'] }}" wire:navigate class="flex h-10 items-center gap-1.5 rounded-full bg-white px-4 text-[13px] font-bold text-navy hover:bg-brand hover:text-white">
                                        {{ __('pages.campaign.view') }}
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
                                    </a>
                                </span>
                            </div>
                        </div>

                        @if ($campaign['products']->isNotEmpty())
                            <div class="relative min-w-0 text-navy">
                                <div x-ref="track" class="carousel auto-cols-[calc((100%-10px)/2)] gap-2.5 sm:auto-cols-[calc((100%-24px)/3)] sm:gap-3 xl:auto-cols-[calc((100%-36px)/4)] 2xl:auto-cols-[calc((100%-48px)/5)]">
                                    @foreach ($campaign['products'] as $product)
                                        @include('partials.product-card', ['product' => $product])
                                    @endforeach
                                </div>
                                @if ($campaign['products']->count() > 2)
                                    <button type="button" x-on:click="scroll(-1)" aria-label="{{ __('shop.prev') }}" class="absolute top-1/2 -left-3 hidden size-10 -translate-y-1/2 place-items-center rounded-full bg-white text-navy shadow-[0_4px_14px_rgba(10,22,51,.25)] hover:bg-brand hover:text-white sm:grid">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 6-6 6 6 6"/></svg>
                                    </button>
                                    <button type="button" x-on:click="scroll(1)" aria-label="{{ __('shop.next') }}" class="absolute top-1/2 -right-3 hidden size-10 -translate-y-1/2 place-items-center rounded-full bg-white text-navy shadow-[0_4px_14px_rgba(10,22,51,.25)] hover:bg-brand hover:text-white sm:grid">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
