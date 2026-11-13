        <section id="brands" class="mt-11 scroll-mt-[140px] sm:mt-[72px]">
            <div class="mb-[22px] flex flex-col gap-1.5">
                <h2 class="m-0 text-xl font-extrabold tracking-[0.3px] sm:text-[28px]">{{ $block['title'] ?? __('shop.brands.title') }}</h2>
            </div>
            <div class="grid grid-cols-2 gap-px overflow-hidden rounded-[18px] border border-line-mid bg-line-mid sm:grid-cols-4 sm:rounded-3xl">
                @foreach ($brands as $brand)
                    <div class="relative flex h-[84px] flex-col justify-center gap-1.5 bg-white px-4 text-navy sm:h-28 sm:px-[26px]">
                        <span class="font-display text-lg leading-none font-extrabold tracking-[-0.6px] sm:text-2xl">{{ $brand['name'] }}</span>
                        <span class="text-xs font-semibold text-subtle">{{ __('shop.products_count', ['count' => $brand['count']]) }}</span>
                    </div>
                @endforeach
            </div>
        </section>
