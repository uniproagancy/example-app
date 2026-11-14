<a href="{{ $campaign['url'] }}" wire:navigate class="group relative isolate flex h-full min-h-[280px] flex-col overflow-hidden rounded-3xl p-6 text-white transition duration-200 hover:-translate-y-1 hover:text-white hover:shadow-[0_24px_48px_-20px_rgba(10,22,51,.45)] sm:p-7 {{ $campaign['background'] }}">
    @if ($campaign['image'])
        <img src="{{ $campaign['image'] }}" alt="" loading="lazy" class="absolute inset-0 -z-10 size-full object-cover transition duration-500 group-hover:scale-105">
        <div class="absolute inset-0 -z-10 bg-[linear-gradient(180deg,rgba(10,22,51,.15)_0%,rgba(10,22,51,.88)_100%)]"></div>
    @else
        <span class="pointer-events-none absolute -right-4 -bottom-10 -z-10 font-display text-[200px] leading-none font-extrabold text-white/6 select-none">%</span>
    @endif

    <div class="flex flex-wrap items-center gap-2">
        <span class="inline-flex h-7 items-center gap-1.5 rounded-full bg-white/12 px-3 text-xs font-semibold backdrop-blur-sm">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            {{ $this->validity($campaign['endsAt']) }}
        </span>
        @if ($campaign['maxDiscount'] > 0)
            <span class="inline-flex h-7 items-center rounded-full bg-sale px-3 text-xs font-extrabold">{{ __('pages.campaign.up_to', ['percent' => $campaign['maxDiscount']]) }}</span>
        @endif
    </div>

    <div class="mt-auto flex flex-col gap-2 pt-10">
        @if ($campaign['badge'])
            <span class="font-display text-[44px] leading-none font-extrabold">{{ $campaign['badge'] }}</span>
        @endif
        <h3 class="m-0 font-caps text-xl leading-tight font-extrabold uppercase">{{ $campaign['title'] }}</h3>
        @if ($campaign['text'])
            <p class="m-0 line-clamp-2 text-sm leading-relaxed text-white/75">{{ $campaign['text'] }}</p>
        @endif
        <span class="mt-3 flex items-center justify-between gap-3">
            <span class="text-[13px] font-semibold text-white/75">{{ trans_choice('pages.campaign.products', $campaign['count'], ['count' => $campaign['count']]) }}</span>
            <span class="flex h-10 items-center gap-1.5 rounded-full bg-white px-4 text-[13px] font-bold text-navy transition group-hover:bg-brand group-hover:text-white">
                {{ __('pages.campaign.view') }}
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
            </span>
        </span>
    </div>
</a>
