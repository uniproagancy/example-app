@php
    [$siteNameMain, $siteNameAccent] = App\Support\Site::nameParts();
    $dark = $dark ?? false;
    $size = $size ?? 'md';
    $logoUrl = App\Support\Site::logoUrl();
    $logoIncludesName = App\Support\Site::logoIncludesName();
@endphp

<span class="flex items-center gap-2.5">
    @if ($logoIncludesName)
        <img src="{{ $logoUrl }}" alt="{{ App\Support\Site::name() }}" @class([
            'block w-auto object-contain',
            'h-[42px] max-w-[190px] sm:max-w-[220px]' => $size === 'md',
            'h-[38px] max-w-[200px]' => $size === 'footer',
            'h-8 max-w-[160px]' => $size === 'sm',
            'rounded-lg bg-white px-2 py-1' => $dark,
        ])>
    @else
        <img src="{{ $logoUrl }}" alt="" @class(['block object-contain', 'size-[42px]' => $size === 'md', 'size-[38px]' => $size === 'footer', 'size-8' => $size === 'sm'])>
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
    @endif
</span>
