<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' — '.App\Support\Site::name() : App\Support\Site::name() }}</title>

        <link rel="icon" type="image/png" href="{{ Vite::asset('resources/images/elio-mark.png') }}">

        @include('partials.hreflang')

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

        @livewireScriptConfig
    </body>
</html>
