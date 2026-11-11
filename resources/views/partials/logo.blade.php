@php
    [$siteNameMain, $siteNameAccent] = App\Support\Site::nameParts();
    $dark = $dark ?? false;
    $size = $size ?? 'md';
@endphp

<span class="flex items-center gap-2.5">
    <img src="{{ Vite::asset('resources/images/elio-mark.png') }}" alt="" @class(['block', 'size-[42px]' => $size === 'md', 'size-[38px]' => $size === 'footer', 'size-8' => $size === 'sm'])>
    <span class="flex flex-col leading-none">
        <span @class([
            'font-display font-extrabold tracking-[-0.4px]',
            'text-[22px]' => $size === 'md',
            'text-xl' => $size === 'footer',
            'text-[19px]' => $size === 'sm',
            'text-white' => $dark,
            'text-navy' => ! $dark,
        ])>{{ $siteNameMain }}<span @class(['text-sky' => $dark, 'text-brand' => ! $dark])>{{ $siteNameAccent }}</span></span>
        @if ($size !== 'sm')
            <span @class([
                'mt-1 text-[10px] tracking-[0.4px]',
                'text-foot-dim' => $dark,
                'hidden text-subtle lg:block' => ! $dark,
            ])>{{ __('shop.tagline') }}</span>
        @endif
    </span>
</span>
