@php
    [$brandMain, $brandAccent] = App\Support\Site::nameParts();
@endphp

<span class="elio-brand">
    <img src="{{ Vite::asset('resources/images/elio-mark.png') }}" alt="" class="elio-brand-mark">
    <span class="elio-brand-name">{{ $brandMain }}<span class="elio-brand-accent">{{ $brandAccent }}</span></span>
    <span class="elio-brand-tag">Admin</span>
</span>
