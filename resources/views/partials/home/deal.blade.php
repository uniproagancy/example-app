        <section class="mt-11 grid min-h-[380px] grid-cols-1 overflow-hidden rounded-[28px] bg-navy text-white sm:mt-16 md:grid-cols-2">
            <div class="flex flex-col justify-center gap-[18px] px-5 py-7 sm:p-11">
                <span class="text-xs font-bold tracking-[0.8px] text-sky uppercase">{{ $block['kicker'] ?? __('shop.deal.kicker') }}</span>
                <h2 class="m-0 font-sans text-[clamp(28px,3vw,38px)] leading-[1.15] font-extrabold normal-case">
                    <a href="{{ $deal['url'] }}" wire:navigate class="text-white hover:text-sky">{{ $deal['name'] }}</a>
                </h2>
                @if ($deal['deal']['text'])
                    <p class="m-0 max-w-[420px] text-base leading-relaxed text-faint">{{ $deal['deal']['text'] }}</p>
                @endif
                <div class="flex items-baseline gap-3.5">
                    <span class="font-display text-[34px] font-extrabold">{{ $deal['priceLabel'] }}</span>
                    @if ($deal['oldLabel'])
                        <span class="text-[17px] text-foot-dim line-through">{{ $deal['oldLabel'] }}</span>
                    @endif
                </div>
                <div x-data="countdown(@js($deal['deal']['endsAt']))" class="flex gap-2.5">
                    @foreach (['hours', 'minutes', 'seconds'] as $unit)
                        <div class="min-w-[76px] rounded-[14px] bg-white/8 py-3 text-center leading-[1.15]">
                            <div class="font-display text-[28px] font-extrabold text-white tabular-nums" x-text="{{ $unit }}">00</div>
                            <div class="text-xs text-faint">{{ __("shop.timer.$unit") }}</div>
                        </div>
                    @endforeach
                </div>
                @if ($deal['inStock'])
                    <button type="button" x-on:click="Livewire.dispatch('cart-add', { id: {{ $deal['id'] }} })" class="mt-1.5 h-[50px] self-start rounded-xl bg-brand px-[26px] text-[15px] font-bold text-white hover:bg-brand-bright">{{ __('shop.add_to_cart_full') }}</button>
                @endif
            </div>
            <a href="{{ $deal['url'] }}" wire:navigate class="relative block min-h-[220px] bg-[radial-gradient(circle_at_50%_50%,rgba(20,180,255,.22),transparent_65%)] p-7 sm:min-h-[300px]">
                @if ($deal['image'])
                    <img src="{{ $deal['image'] }}" alt="{{ $deal['name'] }}" class="absolute inset-0 m-auto max-h-[80%] max-w-[80%] object-contain">
                @else
                    @include('partials.category-icon', ['icon' => $deal['cat'], 'stroke' => 0.6, 'class' => 'absolute inset-0 m-auto h-[70%] text-white/20'])
                @endif
            </a>
        </section>
