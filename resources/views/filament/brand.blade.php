@php
    [$brandMain, $brandAccent] = App\Support\Site::nameParts();
@endphp

<span class="elio-brand">
    @if (App\Support\Site::logoIncludesName())
        <img src="{{ App\Support\Site::logoUrl() }}" alt="{{ App\Support\Site::name() }}" class="elio-brand-logo">
    @else
        <img src="{{ App\Support\Site::logoUrl() }}" alt="" class="elio-brand-mark">
        <span class="elio-brand-name">{{ $brandMain }}<span class="elio-brand-accent">{{ $brandAccent }}</span></span>
    @endif
    <span class="elio-brand-tag">Admin</span>
</span>
