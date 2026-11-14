<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0A1633">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php($pageTitle = (App\Support\Seo::data()['title'] ?? null) ?? (filled($title ?? null) ? $title.' — '.App\Support\Site::name() : App\Support\Site::name()))
        <title>{{ $pageTitle }}</title>

        <link rel="icon" type="image/png" href="{{ App\Support\Site::faviconUrl() }}">

        @include('partials.seo')
        @include('partials.analytics')

        <script>
            window.elioFavorites = @js(auth()->user()?->favoriteIds());
            window.elioFlash = @js(session('toast'));
        </script>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-white pb-[calc(68px+env(safe-area-inset-bottom))] font-sans text-navy antialiased sm:pb-0">
        @include('partials.pixel')
        @include('partials.header')

        {{ $slot }}

        @include('partials.footer')
        @include('partials.overlays')
        @include('partials.cookie-consent')

        @livewireScriptConfig
    </body>
</html>
