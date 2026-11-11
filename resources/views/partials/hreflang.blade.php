@foreach (LaravelLocalization::getLocalesOrder() as $localeCode => $properties)
    <link rel="alternate" hreflang="{{ $localeCode }}" href="{{ ($alternateUrls ?? [])[$localeCode] ?? LaravelLocalization::getLocalizedURL($localeCode, request()->fullUrl(), [], true) }}">
@endforeach
