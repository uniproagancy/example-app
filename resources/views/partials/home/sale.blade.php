        <section id="home-campaigns" x-data="carousel" class="mt-11 scroll-mt-[140px] sm:mt-[72px]">
            <div class="mb-[22px] flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="grid size-12 place-items-center rounded-[14px] bg-sale font-display text-xl font-extrabold text-white">%</span>
                    <div class="flex flex-col gap-1">
                        <h2 class="m-0 text-xl font-extrabold tracking-[0.3px] sm:text-[28px]">{{ __('shop.sale.title') }}</h2>
                        <span class="text-[13px] text-muted">{{ __('shop.sale.subtitle') }}</span>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    @if ($campaigns->count() > 1)
                        <div class="hidden gap-1.5 sm:flex">
                            <button type="button" x-on:click="scroll(-1)" aria-label="{{ __('shop.prev') }}" class="grid size-10 place-items-center rounded-full bg-navy/6 text-navy hover:bg-brand hover:text-white">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 6-6 6 6 6"/></svg>
                            </button>
                            <button type="button" x-on:click="scroll(1)" aria-label="{{ __('shop.next') }}" class="grid size-10 place-items-center rounded-full bg-navy/6 text-navy hover:bg-brand hover:text-white">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
                            </button>
                        </div>
                    @endif
                    <a href="{{ route('promotions') }}" wire:navigate class="flex h-10 items-center rounded-full bg-navy px-[18px] text-[13px] font-bold text-white hover:bg-brand hover:text-white">{{ __('shop.sale.all') }}</a>
                </div>
            </div>
            <div x-ref="track" class="carousel auto-cols-[86%] gap-4 sm:auto-cols-[calc((100%-20px)/2)] sm:gap-5 xl:auto-cols-[calc((100%-40px)/3)]">
                @foreach ($campaigns as $campaign)
                    <div wire:key="home-campaign-{{ $campaign['id'] }}">
                        @include('partials.campaign-card', ['campaign' => $campaign])
                    </div>
                @endforeach
            </div>
        </section>
