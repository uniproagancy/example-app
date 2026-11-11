<?php

namespace App\Http\Middleware;

use App\Services\Facebook\Pixel;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TrackPageView
{
    protected const SKIP = [
        'admin', 'admin/*',
        'livewire/*', '*/livewire-*',
        'payment/*',
        'feed/*',
        'storage/*', 'build/*',
        'sitemap.xml', 'robots.txt', 'up',
    ];

    protected const BOTS = [
        'bot', 'crawl', 'spider', 'slurp', 'facebookexternalhit', 'facebookcatalog',
        'meta-externalagent', 'headless', 'preview', 'monitor', 'curl', 'wget',
        'python-requests', 'go-http-client', 'okhttp', 'axios', 'guzzle',
        'lighthouse', 'pagespeed', 'ahrefs', 'semrush', 'mj12', 'dotbot',
        'petalbot', 'yandex', 'bingpreview', 'whatsapp', 'telegrambot',
    ];

    protected const FBP_DAYS = 90;

    protected const VISITOR_DAYS = 730;

    public function __construct(protected Pixel $pixel) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldTrack($request)) {
            return $next($request);
        }

        if ($this->pixel->enabled()) {
            $this->ensureBrowserIds($request);
        }

        $key = 'pixel:pageview:'.md5($request->ip().$request->userAgent().$request->url());
        $eventId = Cache::get($key);

        if (! $eventId) {
            $eventId = Pixel::eventId('pv');
            Cache::put($key, $eventId, now()->addMinutes(30));

            if ($payload = $this->pixel->capture('PageView', [], $eventId)) {
                $this->pixel->dispatchEvent('PageView', $payload);
            }
        }

        view()->share('fbEventId', $eventId);

        return $next($request);
    }

    protected function ensureBrowserIds(Request $request): void
    {
        $now = (int) (microtime(true) * 1000);

        if (! $request->cookie('_fbp')) {
            $this->remember($request, '_fbp', 'fb.1.'.$now.'.'.random_int(1000000000, 9999999999), self::FBP_DAYS, false);
        }

        $click = $request->query('fbclid');

        if (is_string($click) && $click !== '' && ! str_ends_with((string) $request->cookie('_fbc'), '.'.$click)) {
            $this->remember($request, '_fbc', 'fb.1.'.$now.'.'.$click, self::FBP_DAYS, false);
        }

        if (! $this->pixel->visitorKey()) {
            $this->remember($request, Pixel::VISITOR_COOKIE, bin2hex(random_bytes(16)), self::VISITOR_DAYS, true);
        }
    }

    protected function remember(Request $request, string $name, string $value, int $days, bool $httpOnly): void
    {
        $request->cookies->set($name, $value);

        Cookie::queue(Cookie::make($name, $value, $days * 24 * 60, '/', null, $request->isSecure(), $httpOnly, false, 'lax'));
    }

    protected function shouldTrack(Request $request): bool
    {
        if (! $request->isMethod('GET') || $request->ajax() || $request->wantsJson()) {
            return false;
        }

        if ($request->header('X-Livewire') || $request->is(self::SKIP)) {
            return false;
        }

        return ! $this->isBot($request->userAgent());
    }

    protected function isBot(?string $agent): bool
    {
        return blank($agent) || Str::contains(Str::lower($agent), self::BOTS);
    }
}
