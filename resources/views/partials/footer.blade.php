@php
    $shopLinks = App\Support\CategoryTree::roots()->take(6);
    $socialLinks = App\Support\Site::socials();
    $pageLinks = App\Models\Page::menu();
    $pageUrl = fn (string $slug): string => $pageLinks->firstWhere('slug', $slug)['url'] ?? '#';
    $extraPages = $pageLinks->where('in_footer', true)->whereNotIn('slug', ['delivery', 'returns', 'warranty', 'installments', 'terms', 'privacy']);
    $socialLabels = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn'];
    $socialIcons = [
        'facebook' => '<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v3H7v4h3v6h4v-6h3l1-4h-4V8z"/>',
        'instagram' => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.7" r="1" fill="currentColor" stroke="none"/>',
        'tiktok' => '<path d="M14 3v11a4 4 0 1 1-4-4"/><path d="M14 3c.5 3 2.5 5 5.5 5"/>',
        'youtube' => '<rect x="2.5" y="5.5" width="19" height="13" rx="4"/><path d="m10 9.5 5 2.5-5 2.5z" fill="currentColor" stroke="none"/>',
        'linkedin' => '<rect x="3.5" y="3.5" width="17" height="17" rx="3"/><path d="M8 10.5v6M8 7.5v.01M12 16.5v-3.5a2 2 0 0 1 4 0v3.5M12 10.5v6"/>',
    ];
@endphp

<footer class="mt-20 border-t-4 border-brand bg-navy text-foot">
    <div class="mx-auto grid max-w-[1480px] grid-cols-2 gap-x-4 gap-y-7 px-4 pt-10 pb-7 sm:px-6 sm:pt-12 sm:pb-9 lg:grid-cols-[repeat(auto-fit,minmax(200px,1fr))] lg:gap-10 lg:px-8 lg:pt-14 lg:pb-11">
        <div class="col-span-2 flex flex-col gap-[18px] lg:col-span-1">
            @include('partials.logo', ['dark' => true, 'size' => 'footer'])
            @if (App\Support\Site::phone())
                <a href="{{ App\Support\Site::phoneHref() }}" class="font-display text-lg font-extrabold tracking-[0.3px] text-white hover:text-white">{{ App\Support\Site::phone() }}</a>
            @endif
            @if ($socialLinks)
                <div class="flex gap-2">
                    @foreach ($socialLinks as $network => $socialUrl)
                        <a href="{{ $socialUrl }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $socialLabels[$network] }}" class="grid size-10 place-items-center rounded-xl border border-white/14 text-white hover:border-brand hover:bg-brand hover:text-white">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $socialIcons[$network] !!}</svg>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="flex flex-col gap-3">
            <span class="mb-2 border-b border-white/10 pb-2.5 text-[15px] font-bold text-white">{{ __('shop.footer.shop') }}</span>
            @foreach ($shopLinks as $category)
                <a href="{{ App\Support\CategoryTree::url($category) }}" wire:navigate class="text-sm font-medium text-foot hover:text-white">{{ $category['title'] }}</a>
            @endforeach
            <a href="#" class="text-sm font-semibold text-[#FF6B6B] hover:text-white">{{ __('shop.nav.discounts') }}</a>
        </div>

        <div class="flex flex-col gap-3">
            <span class="mb-2 border-b border-white/10 pb-2.5 text-[15px] font-bold text-white">{{ __('shop.footer.help') }}</span>
            <a href="{{ $pageUrl('delivery') }}" wire:navigate class="text-sm font-medium text-foot hover:text-white">{{ __('shop.footer.delivery') }}</a>
            <a href="{{ $pageUrl('warranty') }}" wire:navigate class="text-sm font-medium text-foot hover:text-white">{{ __('shop.footer.warranty') }}</a>
            @foreach ($extraPages as $extraPage)
                <a href="{{ $extraPage['url'] }}" wire:navigate class="text-sm font-medium text-foot hover:text-white">{{ $extraPage['title'] }}</a>
            @endforeach
            <a href="{{ route('about') }}" wire:navigate class="text-sm font-medium text-foot hover:text-white">{{ __('shop.nav.about') }}</a>
            <a href="{{ route('contact') }}" wire:navigate class="text-sm font-medium text-foot hover:text-white">{{ __('shop.nav.contact') }}</a>
        </div>

        <div class="col-span-2 flex flex-col gap-3 lg:col-span-1">
            <span class="mb-2 border-b border-white/10 pb-2.5 text-[15px] font-bold text-white">{{ __('shop.footer.payment_methods') }}</span>
            <div class="flex flex-wrap gap-2">
                <span class="flex h-[34px] items-center rounded-lg bg-white px-3 font-display text-[15px] font-extrabold tracking-[0.5px] text-[#1A1F71] italic">VISA</span>
                <span class="flex h-[34px] items-center rounded-lg bg-white px-[11px]"><span class="size-[18px] rounded-full bg-[#EB001B]"></span><span class="-ml-[7px] size-[18px] rounded-full bg-[#F79E1B] opacity-90"></span></span>
                <span class="flex h-[34px] items-center rounded-lg bg-white px-3 font-ui text-[13px] font-bold text-black">Apple Pay</span>
                <span class="flex h-[34px] items-center rounded-lg bg-white px-3 font-ui text-[13px] font-bold text-[#3C4043]">Google Pay</span>
            </div>
            <span class="mt-3.5 mb-1 border-b border-white/10 pb-2.5 text-[15px] font-bold text-white">{{ __('shop.footer.installments') }}</span>
            <div class="flex flex-col gap-2">
                @foreach ([['BOG', '#FF6B00', 'bog'], ['TBC', '#00AEEF', 'tbc'], ['CR', '#2A6FB5', 'credo']] as [$mark, $color, $key])
                    <a href="{{ $pageUrl('installments') }}" wire:navigate class="flex items-center gap-3 py-1 text-sm font-medium text-foot hover:text-white">
                        <span class="grid size-7 shrink-0 place-items-center rounded-full font-display text-[11px] font-extrabold text-white" style="background: {{ $color }}">{{ $mark }}</span>
                        {{ __("shop.footer.$key") }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="mx-auto flex max-w-[1480px] flex-wrap items-center justify-between gap-4 border-t border-white/10 p-4 text-[13px] sm:px-6 sm:pt-5 sm:pb-[26px] lg:px-8">
        <span class="text-foot-dim">{{ __('shop.footer.rights', ['year' => date('Y'), 'name' => App\Support\Site::name()]) }}</span>
        <div class="flex flex-wrap items-center gap-6">
            <a href="{{ $pageUrl('terms') }}" wire:navigate class="text-foot hover:text-white">{{ __('shop.footer.terms') }}</a>
            <a href="{{ $pageUrl('privacy') }}" wire:navigate class="text-foot hover:text-white">{{ __('shop.footer.privacy') }}</a>
            <span class="text-foot">{{ __('shop.footer.location') }}</span>
            <div class="sm:hidden">
                @include('partials.language-switcher')
            </div>
        </div>
    </div>
</footer>
