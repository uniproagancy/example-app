<?php

namespace App\Services\Import\Drivers\Zoommer;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class ZoommerClient
{
    protected const BLOCKED_STATUSES = [401, 403, 429, 503];

    protected Client $client;

    protected string $apiUrl;

    public function __construct(protected array $config = [])
    {
        $this->apiUrl = rtrim((string) ($config['api_url'] ?? config('services.zoommer.api_url')), '/').'/';

        $this->client = new Client([
            'timeout' => $config['timeout'] ?? config('import.request_timeout'),
            'connect_timeout' => 10,
            'http_errors' => false,
            'force_ip_resolve' => 'v4',
            'headers' => $this->headers(),
            'curl' => [
                CURLOPT_DNS_CACHE_TIMEOUT => 300,
            ],
        ]);
    }

    protected function headers(): array
    {
        return array_filter([
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Encoding' => 'gzip, deflate',
            'Referer' => config('services.zoommer.site'),
            'User-Agent' => config('services.zoommer.user_agent') ?: (string) config('import.user_agent'),
            'os' => 'web',
            'sec-ch-ua' => config('services.zoommer.sec_ch_ua'),
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => '"Windows"',
            'sec-fetch-dest' => 'empty',
            'sec-fetch-mode' => 'cors',
            'sec-fetch-site' => 'same-origin',
            'Cookie' => static::cookie(),
            'X-Proxy-Cookie' => static::workerUrl() ? static::cookie() : null,
        ]);
    }

    public static function accessToken(): ?string
    {
        $token = trim(trim((string) config('services.zoommer.access_token')), '"\'');
        $token = preg_replace('/^(Bearer\s+|zoommer-access_token=)/i', '', $token);

        if ($token === '') {
            return null;
        }

        return preg_match('/%[0-9A-Fa-f]{2}/', $token) ? $token : rawurlencode($token);
    }

    public static function workerUrl(): ?string
    {
        $url = trim((string) config('services.zoommer.worker_url'));

        return $url !== '' ? rtrim($url, '/') : null;
    }

    public static function cookie(): string
    {
        return implode('; ', array_filter([
            'zoommer-cookie_agreed=true',
            ($token = static::accessToken()) ? 'zoommer-access_token='.$token : null,
            ($clearance = trim((string) config('services.zoommer.cf_clearance'))) !== '' ? 'cf_clearance='.$clearance : null,
        ]));
    }

    protected function report(string $id, ?string $locale, int $status, float $startedAt, string $body): void
    {
        $context = [
            'product' => $id,
            'locale' => $locale,
            'status' => $status,
            'ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'token_sent' => static::accessToken() !== null,
            'route' => static::workerUrl() ? 'worker' : 'direct',
        ];

        if ($status !== 200) {
            $context['response'] = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($body))), 0, 400);
        }

        Log::channel('import')->log($status === 200 ? 'info' : 'warning', 'zoommer request', $context);
    }

    protected function endpoint(string $id): string
    {
        if (! $worker = static::workerUrl()) {
            return $this->apiUrl.'v1/Products/details?productId='.rawurlencode($id);
        }

        return $worker.'?'.http_build_query(array_filter([
            'type' => 'product',
            'productId' => $id,
            'accessToken' => static::accessToken(),
        ]));
    }

    public function fetch(string $id, array $locales = ['ka']): array
    {
        $out = [];

        foreach ($locales as $locale) {
            $startedAt = microtime(true);
            $response = $this->client->get($this->endpoint($id), ['headers' => ['Accept-Language' => $locale]]);
            $status = $response->getStatusCode();
            $body = (string) $response->getBody();
            $this->report($id, $locale, $status, $startedAt, $body);

            if (in_array($status, self::BLOCKED_STATUSES, true) || $this->isChallenge($body)) {
                throw new BlockedByZoommer($this->refusal($status, $body));
            }

            if ($status !== 200) {
                continue;
            }

            $data = json_decode($body, true);

            if (! empty($data['product'])) {
                $out[$locale] = $data;
            }
        }

        return $out;
    }

    protected function refusal(int $status, string $body): string
    {
        $reason = match (true) {
            $status === 401 => 'The zoommer-access_token cookie is missing or no longer valid. Copy a fresh one from a signed-in zoommer.ge session into ZOOMMER_ACCESS_TOKEN.',
            $status === 403 || $this->isChallenge($body) => static::workerUrl()
                ? 'Cloudflare refused the worker as well. Run php artisan import:check-zoommer to see which route answers.'
                : 'Cloudflare refused this server, not the token. Set ZOOMMER_WORKER_URL and run php artisan config:clear.',
            $status === 429 => 'Too many requests. Lower IMPORT_RATE_PER_MINUTE or run fewer workers.',
            default => 'Zoommer is refusing requests for now; this is usually temporary.',
        };

        $route = static::workerUrl() ? 'through the worker' : 'directly';

        return "Zoommer refused the request (HTTP {$status}, asked {$route}). {$reason}";
    }

    protected function isChallenge(string $body): bool
    {
        return $body !== ''
            && ! str_starts_with(ltrim($body), '{')
            && (bool) preg_match('/cf-browser-verification|Just a moment|cf_chl|Attention Required/i', $body);
    }
}
