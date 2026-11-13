@php
    $seoDescription = App\Support\Seo::description();
    $seoImage = App\Support\Seo::image();
    $seoCanonical = App\Support\Seo::canonical();
    $seoAlternates = App\Support\Seo::alternates();
    $seoIndexable = App\Support\Seo::isIndexable();
    $seo = App\Support\Seo::data();
    $seoType = $seo['type'] ?? 'website';
    $ogLocales = ['ka' => 'ka_GE', 'en' => 'en_US', 'ru' => 'ru_RU'];
    $verification = App\Models\Setting::get('google_site_verification');
@endphp
<meta name="description" content="{{ $seoDescription }}">
<meta name="robots" content="{{ $seoIndexable ? 'index, follow, max-image-preview:large' : 'noindex, follow' }}">
@if ($seoIndexable)
    <link rel="canonical" href="{{ $seoCanonical }}">
    @foreach ($seoAlternates as $localeCode => $alternateUrl)
        <link rel="alternate" hreflang="{{ $localeCode }}" href="{{ $alternateUrl }}">
    @endforeach
    @isset($seoAlternates[App\Support\Locales::default()])
        <link rel="alternate" hreflang="x-default" href="{{ $seoAlternates[App\Support\Locales::default()] }}">
    @endisset
@endif
@if (filled($verification))
    <meta name="google-site-verification" content="{{ $verification }}">
@endif
<meta property="og:site_name" content="{{ App\Support\Site::name() }}">
<meta property="og:type" content="{{ $seoType }}">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:url" content="{{ $seoCanonical }}">
<meta property="og:image" content="{{ $seoImage }}">
<meta property="og:locale" content="{{ $ogLocales[app()->getLocale()] ?? app()->getLocale() }}">
@if ($seoType === 'product' && isset($seo['price']))
    <meta property="product:price:amount" content="{{ $seo['price'] }}">
    <meta property="product:price:currency" content="GEL">
@endif
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $pageTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image" content="{{ $seoImage }}">
@if ($seoIndexable)
    <script type="application/ld+json">{!! json_encode(App\Support\Seo::graph(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endif
