            <section
                id="sec-{{ $section['id'] }}"
                wire:key="section-{{ $section['id'] }}"
                x-data="productSection"
                class="mt-11 scroll-mt-[140px] sm:mt-16"
            >
                <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-center gap-3">
                            <h2 class="m-0 text-xl leading-[1.3] font-extrabold tracking-[0.3px] sm:text-2xl">
                                <a href="{{ $section['url'] }}" wire:navigate class="text-navy hover:text-brand">{{ $section['name'] }}</a>
                            </h2>
                            @if ($section['count'])
                                <span class="rounded-full bg-soft px-2.5 py-1 font-display text-xs font-bold text-muted">{{ $section['count'] }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="hidden gap-1.5 sm:flex">
                            <button type="button" x-on:click="scroll(-1)" aria-label="{{ __('shop.prev') }}" class="grid size-10 place-items-center rounded-full bg-soft text-navy hover:bg-brand hover:text-white">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 6-6 6 6 6"/></svg>
                            </button>
                            <button type="button" x-on:click="scroll(1)" aria-label="{{ __('shop.next') }}" class="grid size-10 place-items-center rounded-full bg-soft text-navy hover:bg-brand hover:text-white">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
                            </button>
                        </div>
                        <a href="{{ $section['url'] }}" wire:navigate class="flex h-[38px] items-center gap-1.5 rounded-full bg-tint px-[18px] font-caps text-[13px] font-bold tracking-[0.3px] text-brand uppercase hover:bg-brand hover:text-white">{{ __('shop.all_in_category') }} →</a>
                    </div>
                </div>

                <div class="mb-[22px] hidden max-w-full flex-wrap gap-0.5 rounded-full bg-soft p-1 sm:inline-flex">
                    @foreach (array_merge([''], $section['chips']) as $chip)
                        <button
                            type="button"
                            x-on:click="select(@js($chip))"
                            class="h-[34px] shrink-0 rounded-full px-4 text-[13px] font-bold whitespace-nowrap transition"
                            :class="active === @js($chip) ? 'bg-white text-navy shadow-[0_1px_4px_rgba(10,22,51,.12)]' : 'bg-transparent text-muted'"
                        >{{ $chip === '' ? __('shop.all') : App\Support\Catalog::label($chip) }}</button>
                    @endforeach
                </div>

                <div x-ref="track" class="carousel auto-cols-[calc((100%-10px)/2)] gap-2.5 sm:auto-cols-[calc((100%-24px)/3)] sm:gap-3 md:auto-cols-[calc((100%-36px)/4)] lg:auto-cols-[calc((100%-48px)/5)] xl:auto-cols-[calc((100%-60px)/6)]">
                    @foreach ($section['products'] as $product)
                        @include('partials.product-card', ['product' => $product, 'filter' => true])
                    @endforeach
                </div>
            </section>
