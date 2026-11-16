<section class="relative isolate overflow-hidden rounded-[28px] px-6 py-9 text-white sm:px-12 sm:py-12 {{ $campaign['background'] }}">
    @if ($campaign['image'])
        <img src="{{ $campaign['image'] }}" alt="" class="absolute inset-0 -z-10 size-full object-cover">
        <div class="absolute inset-0 -z-10 bg-[linear-gradient(90deg,rgba(10,22,51,.92)_0%,rgba(10,22,51,.7)_45%,rgba(10,22,51,.15)_100%)]"></div>
    @else
        <div class="pointer-events-none absolute -top-[180px] -right-[120px] -z-10 size-[460px] rounded-full bg-[radial-gradient(circle,rgba(20,180,255,.28),transparent_65%)]"></div>
        <span class="pointer-events-none absolute -right-6 -bottom-16 -z-10 font-display text-[clamp(160px,22vw,300px)] leading-none font-extrabold text-white/6 select-none">%</span>
    @endif

    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
        <div class="flex max-w-[680px] flex-col gap-3">
            <div class="flex flex-wrap items-center gap-2">
                @if ($campaign['endsAt'])
                    <span class="inline-flex h-8 items-center gap-1.5 rounded-full bg-white/12 px-3 text-xs font-semibold backdrop-blur-sm">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                        {{ App\Models\Campaign::validity($campaign['endsAt']) }}
                    </span>
                @endif
                @if ($campaign['maxDiscount'] > 0)
                    <span class="inline-flex h-8 items-center rounded-full bg-sale px-3 text-xs font-extrabold">{{ __('pages.campaign.up_to', ['percent' => $campaign['maxDiscount']]) }}</span>
                @endif
            </div>
            @if ($campaign['badge'])
                <span class="font-display text-[clamp(40px,6vw,72px)] leading-none font-extrabold">{{ $campaign['badge'] }}</span>
            @endif
            <{{ $heading }} class="m-0 font-caps text-[clamp(26px,3.4vw,44px)] leading-[1.15] font-extrabold uppercase">{{ $campaign['title'] }}</{{ $heading }}>
            @if ($campaign['text'])
                <p class="m-0 max-w-[560px] text-[15px] leading-relaxed text-white/80">{{ $campaign['text'] }}</p>
            @endif
        </div>

        @if ($campaign['endsAt'])
            <div x-data="campaignCountdown(@js($campaign['endsAt']))" class="flex flex-col gap-2">
                <span class="text-xs font-bold tracking-[0.6px] text-white/70 uppercase">{{ __('pages.campaign.ends_in') }}</span>
                <div class="flex gap-2">
                    @foreach (['days', 'hours', 'minutes', 'seconds'] as $unit)
                        <div class="min-w-[64px] rounded-[14px] bg-white/10 py-2.5 text-center leading-[1.15] backdrop-blur-sm sm:min-w-[72px]">
                            <div class="font-display text-2xl font-extrabold tabular-nums sm:text-[28px]" x-text="{{ $unit }}">00</div>
                            <div class="text-[11px] text-white/70">{{ __("pages.campaign.timer.$unit") }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>
