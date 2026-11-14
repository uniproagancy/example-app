@php
    $gaId = auth()->user()?->is_admin ? null : App\Support\Consent::ga4Id();
@endphp
<script>
    window.elioConsent = (document.cookie.match(/(?:^|; )cookie_consent=(granted|denied)/) || [])[1] || null;
</script>
@if ($gaId)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };

        if (!window.elioGaReady) {
            window.elioGaReady = true;
            const granted = window.elioConsent === 'granted' ? 'granted' : 'denied';

            gtag('consent', 'default', {
                analytics_storage: granted,
                ad_storage: granted,
                ad_user_data: granted,
                ad_personalization: granted,
            });
            gtag('js', new Date());
            gtag('config', @json($gaId), { send_page_view: false });

            document.addEventListener('livewire:navigated', () => gtag('event', 'page_view', { page_location: location.href, page_title: document.title }));
        }
    </script>
@endif
