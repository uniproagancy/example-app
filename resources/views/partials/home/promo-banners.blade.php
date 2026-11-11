@php
    $promoThemes = [
        'navy' => ['bg' => 'bg-navy', 'fg' => 'text-white', 'muted' => 'text-faint', 'accent' => 'text-sky', 'badge' => 'text-sale', 'icon' => 'text-white/15'],
        'tint' => ['bg' => 'bg-tint', 'fg' => 'text-navy', 'muted' => 'text-[#4A5A78]', 'accent' => 'text-brand', 'badge' => 'text-brand', 'icon' => 'text-brand/15'],
        'field' => ['bg' => 'bg-field', 'fg' => 'text-navy', 'muted' => 'text-[#4A5A78]', 'accent' => 'text-brand', 'badge' => 'text-brand', 'icon' => 'text-brand/15'],
    ];
    $leadPromo = $promos->first();
    $leadTheme = $promoThemes[$leadPromo['theme']];
    $otherPromos = $promos->slice(1);
@endphp

        <section class="mt-11 flex flex-col gap-5 sm:mt-14">
            <a href="{{ $leadPromo['href'] ?? '#' }}" @if ($leadPromo['href']) wire:navigate @endif class="grid min-h-[160px] grid-cols-1 overflow-hidden rounded-3xl hover:opacity-95 {{ $leadTheme['bg'] }} sm:grid-cols-[1.4fr_1fr]">
                <div class="flex flex-col justify-center gap-2 p-6 sm:p-10">
                    @if ($leadPromo['badge'])
                        <span class="font-display text-[clamp(36px,5vw,64px)] leading-none font-extrabold {{ $leadTheme['badge'] }}">{{ $leadPromo['badge'] }}</span>
                    @endif
                    <span class="font-caps text-xl font-extrabold uppercase sm:text-2xl {{ $leadTheme['fg'] }}">{{ $leadPromo['title'] }}</span>
                    <span class="text-sm {{ $leadTheme['muted'] }}">{{ collect([$leadPromo['text'], $leadPromo['note']])->filter()->join(' · ') }}</span>
                </div>
                <div class="relative hidden bg-[radial-gradient(circle_at_50%_50%,rgba(20,180,255,.3),transparent_65%)] sm:block">
                    @include('partials.category-icon', ['icon' => $leadPromo['icon'], 'stroke' => 0.8, 'class' => 'absolute inset-0 m-auto h-[70%] '.$leadTheme['icon']])
                </div>
            </a>

            @if ($otherPromos->isNotEmpty())
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    @foreach ($otherPromos as $promo)
                        <a href="{{ $promo['href'] ?? '#' }}" @if ($promo['href']) wire:navigate @endif class="flex min-h-[160px] flex-col justify-center gap-2 rounded-3xl p-6 hover:opacity-95 sm:min-h-[220px] sm:p-9 {{ $promoThemes[$promo['theme']]['bg'] }}">
                            @if ($promo['badge'])
                                <span class="font-display text-[44px] leading-none font-extrabold {{ $promoThemes[$promo['theme']]['badge'] }}">{{ $promo['badge'] }}</span>
                            @endif
                            <span class="font-caps text-xl font-extrabold uppercase {{ $promoThemes[$promo['theme']]['fg'] }}">{{ $promo['title'] }}</span>
                            @if ($promo['text'])
                                <span class="text-sm {{ $promoThemes[$promo['theme']]['muted'] }}">{{ $promo['text'] }}</span>
                            @endif
                            <span class="mt-1 text-[13px] font-bold {{ $promoThemes[$promo['theme']]['accent'] }}">{{ collect([$promo['note'], __('shop.promos.more')])->filter()->join(' · ') }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
