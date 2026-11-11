@php
    try {
        $errorLogo = view('partials.logo')->render();
    } catch (Throwable) {
        $errorLogo = null;
    }

    try {
        $errorHomeUrl = route('home');
    } catch (Throwable) {
        $errorHomeUrl = url('/');
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">

        <title>@yield('title')</title>

        <link rel="icon" type="image/png" href="{{ Vite::asset('resources/images/elio-mark.png') }}">

        @fonts
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen bg-white font-sans text-navy antialiased">
        <div class="relative flex min-h-screen flex-col overflow-hidden">
            <div class="pointer-events-none absolute -top-[220px] -right-[180px] size-[560px] rounded-full bg-[radial-gradient(circle,rgba(11,99,246,.12),transparent_65%)]"></div>
            <div class="pointer-events-none absolute -bottom-[240px] -left-[200px] size-[520px] rounded-full bg-[radial-gradient(circle,rgba(20,180,255,.12),transparent_65%)]"></div>

            <header class="relative mx-auto flex w-full max-w-[1480px] items-center px-4 py-5 sm:px-6 lg:px-8">
                <a href="{{ $errorHomeUrl }}" class="hover:text-navy">
                    @if ($errorLogo)
                        {!! $errorLogo !!}
                    @else
                        <img src="{{ Vite::asset('resources/images/elio-mark.png') }}" alt="" class="size-[42px]">
                    @endif
                </a>
            </header>

            <main class="relative mx-auto flex w-full max-w-[640px] flex-1 flex-col items-center justify-center px-4 pt-6 pb-20 text-center">
                <span class="rounded-full bg-tint px-3.5 py-1.5 text-xs font-bold tracking-[0.6px] text-brand uppercase">{{ __('errors.label', ['code' => trim($__env->yieldContent('code'))]) }}</span>
                <span class="mt-6 bg-[linear-gradient(120deg,#0A1633_10%,#0B63F6_60%,#14B4FF)] bg-clip-text font-display text-[112px] leading-none font-extrabold tracking-[-6px] text-transparent sm:text-[160px]">@yield('code')</span>
                <h1 class="mt-4 mb-0 text-[26px] leading-tight font-extrabold tracking-[0.3px] sm:text-[32px]">@yield('title')</h1>
                <p class="mt-3 mb-0 max-w-[460px] text-[15px] leading-relaxed text-muted">@yield('message')</p>

                <div class="mt-8 flex flex-wrap items-center justify-center gap-2.5">
                    <a href="{{ $errorHomeUrl }}" class="flex h-12 items-center gap-2 rounded-full bg-brand px-6 text-sm font-bold text-white hover:bg-brand-dark hover:text-white">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11 12 4l9 7"/><path d="M5 10v10h14V10"/></svg>
                        {{ __('errors.home') }}
                    </a>
                    @hasSection('reload')
                        <button type="button" onclick="window.location.reload()" class="flex h-12 items-center gap-2 rounded-full border-[1.5px] border-line bg-white px-6 text-sm font-bold text-navy hover:border-brand hover:text-brand">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 5v6h-6"/></svg>
                            {{ __('errors.reload') }}
                        </button>
                    @else
                        <button type="button" onclick="window.history.length > 1 ? window.history.back() : window.location.assign(@js($errorHomeUrl))" class="flex h-12 items-center gap-2 rounded-full border-[1.5px] border-line bg-white px-6 text-sm font-bold text-navy hover:border-brand hover:text-brand">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 6-6 6 6 6"/></svg>
                            {{ __('errors.back') }}
                        </button>
                    @endif
                </div>
            </main>
        </div>
    </body>
</html>
