@php
    $localeLabels = ['ka' => 'ქარ', 'en' => 'ENG'];
    $currentLocale = LaravelLocalization::getCurrentLocale();
@endphp

<nav aria-label="{{ __('messages.language') }}" class="flex shrink-0 items-center gap-1 rounded-full bg-soft p-1 {{ $class ?? '' }}">
    @foreach (LaravelLocalization::getLocalesOrder() as $localeCode => $properties)
        <a
            rel="alternate"
            hreflang="{{ $localeCode }}"
            href="{{ ($alternateUrls ?? [])[$localeCode] ?? LaravelLocalization::getLocalizedURL($localeCode, request()->fullUrl(), [], true) }}"
            title="{{ $properties['native'] }}"
            @if ($localeCode === $currentLocale) aria-current="true" @endif
            @class([
                'flex h-[30px] items-center rounded-full px-3 text-xs font-bold tracking-[0.3px]',
                'bg-white text-navy shadow-[0_1px_4px_rgba(10,22,51,.12)] hover:text-navy' => $localeCode === $currentLocale,
                'text-muted hover:text-brand' => $localeCode !== $currentLocale,
            ])
        >{{ $localeLabels[$localeCode] ?? strtoupper($localeCode) }}</a>
    @endforeach
</nav>
