@php
    $pixelService = app(App\Services\Facebook\Pixel::class);
    $pixelId = $pixelService->staff() ? null : $pixelService->pixelId();
    $pixelMatch = [];
    $pixelQueued = $pixelId ? array_map(fn (array $event) => [$event['event'], $event['data'] ?? [], $event['id'] ?? null], $pixelService->pullFlashed()) : [];

    if ($pixelId && ($pixelUser = auth()->user())) {
        $pixelMatch = array_filter([
            'em' => $pixelUser->email,
            'ph' => App\Support\Phone::digits($pixelUser->phone),
            'fn' => $pixelUser->firstName(),
            'external_id' => hash('sha256', (string) $pixelUser->id),
            'country' => 'ge',
        ]);
    } elseif ($pixelId && ($pixelVisitor = $pixelService->visitorKey())) {
        $pixelMatch = [
            'external_id' => hash('sha256', $pixelVisitor),
            'country' => 'ge',
        ];
    }
@endphp

@if ($pixelId)
    <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window,document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');

        window.ELIO_PIXEL = {
            id: @json($pixelId),
            match: @json((object) $pixelMatch),
            pageViewId: @json($fbEventId ?? null),
            started: false,
            queue: [...((window.ELIO_PIXEL && window.ELIO_PIXEL.queue) || []), ...@json($pixelQueued)],
        };

        window.pixelTrack = function (event, data, eventId) {
            if (!window.ELIO_PIXEL.started) {
                window.ELIO_PIXEL.queue.push([event, data, eventId]);
                return;
            }

            fbq('track', event, data || {}, eventId ? { eventID: eventId } : undefined);
        };

        (function () {
            const p = window.ELIO_PIXEL;

            if (!window.ELIO_PIXEL_READY) {
                fbq('consent', window.elioConsent === 'granted' ? 'grant' : 'revoke');
                Object.keys(p.match).length ? fbq('init', p.id, p.match) : fbq('init', p.id);
                window.ELIO_PIXEL_READY = true;
            }

            p.started = true;
            fbq('track', 'PageView', {}, p.pageViewId ? { eventID: p.pageViewId } : undefined);
            p.queue.splice(0).forEach(([event, data, eventId]) => window.pixelTrack(event, data, eventId));
        })();

        if (!window.ELIO_PIXEL_LISTENING) {
            window.ELIO_PIXEL_LISTENING = true;

            document.addEventListener('livewire:init', () => {
                Livewire.on('pixel', (payload) => {
                    const e = Array.isArray(payload) ? payload[0] : payload;

                    if (e && e.event) {
                        window.pixelTrack(e.event, e.data, e.id);
                    }
                });
            });
        }
    </script>

    <noscript>
        <img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id={{ $pixelId }}&ev=PageView&noscript=1">
    </noscript>
@endif
