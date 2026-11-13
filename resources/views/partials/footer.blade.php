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
                <span class="flex h-[34px] items-center gap-[3px] rounded-lg bg-white px-3 text-black" aria-label="Apple Pay" role="img">
                    <svg width="14" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="-mt-0.5"><path d="M12.152 6.896c-.948 0-2.415-1.078-3.96-1.04-2.04.027-3.91 1.183-4.961 3.014-2.117 3.675-.546 9.103 1.519 12.09 1.013 1.454 2.208 3.09 3.792 3.039 1.52-.065 2.09-.987 3.935-.987 1.831 0 2.35.987 3.96.948 1.637-.026 2.676-1.48 3.676-2.948 1.156-1.688 1.636-3.325 1.662-3.415-.039-.013-3.182-1.221-3.22-4.857-.026-3.04 2.48-4.494 2.597-4.559-1.429-2.09-3.623-2.324-4.39-2.376-2-.156-3.675 1.09-4.61 1.09zM15.53 3.83c.843-1.012 1.4-2.427 1.245-3.83-1.207.052-2.662.805-3.532 1.818-.78.896-1.454 2.338-1.273 3.714 1.338.104 2.715-.688 3.559-1.701"/></svg>
                    <span class="font-sans text-[15px] leading-none font-semibold tracking-[-0.2px]">Pay</span>
                </span>
                <span class="flex h-[34px] items-center gap-1 rounded-lg bg-white px-3" aria-label="Google Pay" role="img">
                    <svg width="16" height="16" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FBBC05" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/><path fill="#EA4335" d="m6.306 14.691 6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"/><path fill="#34A853" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238A11.91 11.91 0 0 1 24 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"/><path fill="#4285F4" d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 0 1-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/></svg>
                    <span class="font-sans text-[15px] leading-none font-medium text-[#5F6368]">Pay</span>
                </span>
            </div>
            <span class="mt-3.5 mb-1 border-b border-white/10 pb-2.5 text-[15px] font-bold text-white">{{ __('shop.footer.installments') }}</span>
            <div class="flex flex-wrap gap-2">
                @foreach (['bog' => 'bog-1.png', 'tbc' => 'tbc-1.png', 'credo' => 'credo-1.png'] as $key => $logo)
                    <a href="{{ $pageUrl('installments') }}" wire:navigate title="{{ __("shop.footer.$key") }}" class="flex h-12 items-center rounded-lg bg-white px-1 transition hover:-translate-y-px hover:shadow-[0_6px_16px_-6px_rgba(0,0,0,.5)]">
                        <img src="{{ asset('img/payments/'.$logo) }}" alt="{{ __("shop.footer.$key") }}" loading="lazy" width="129" height="42" class="h-[42px] w-auto">
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
