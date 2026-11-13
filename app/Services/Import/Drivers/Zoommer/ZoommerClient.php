<?php

namespace App\Services\Import\Drivers\Zoommer;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
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
        ]);
    }

    protected function headers(): array
    {
        return array_filter([
            'Accept' => 'application/json',
            'User-Agent' => (string) config('import.user_agent'),
            'Cookie' => static::cookie(),
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

    public static function cookie(): string
    {
        return implode('; ', array_filter([
            'zoommer-cookie_agreed=true',
            ($token = static::accessToken()) ? 'zoommer-access_token='.$token : null,
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
        ];

        if ($status !== 200) {
            $context['response'] = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($body))), 0, 400);
        }

        Log::channel('import')->log($status === 200 ? 'info' : 'warning', 'zoommer request', $context);
    }

    protected function endpoint(string $id): string
    {
        return $this->apiUrl.'v1/Products/details?productId='.rawurlencode($id);
    }

    public function fetch(string $id, array $locales = ['ka', 'en']): array
    {
        $out = [];

        foreach ($locales as $locale) {
            $startedAt = microtime(true);
            $response = $this->client->get($this->endpoint($id), ['headers' => ['Accept-Language' => $locale]]);
            $status = $response->getStatusCode();
            $body = (string) $response->getBody();
            $this->report($id, $locale, $status, $startedAt, $body);

            if (in_array($status, self::BLOCKED_STATUSES, true) || $this->isChallenge($body)) {
                throw new BlockedByZoommer($this->refusal($status));
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

    public function fetchMany(array $ids, int $concurrency = 5): array
    {
        $found = [];

        $requests = function () use ($ids) {
            foreach ($ids as $id) {
                yield $id => new Request('GET', $this->endpoint((string) $id), ['Accept-Language' => 'ka']);
            }
        };

        $startedAt = microtime(true);

        (new Pool($this->client, $requests(), [
            'concurrency' => $concurrency,
            'fulfilled' => function ($response, $id) use (&$found, $startedAt) {
                $body = (string) $response->getBody();
                $this->report((string) $id, 'ka', $response->getStatusCode(), $startedAt, $body);

                if ($response->getStatusCode() !== 200) {
                    return;
                }

                $data = json_decode($body, true);

                if (! empty($data['product'])) {
                    $found[$id] = $data;
                }
            },
        ]))->promise()->wait();

        return $found;
    }

    protected function refusal(int $status): string
    {
        $reason = match ($status) {
            401 => 'The zoommer-access_token cookie is missing or no longer valid. Copy a fresh one from a signed-in zoommer.ge session into ZOOMMER_ACCESS_TOKEN.',
            429 => 'Too many requests. Lower IMPORT_RATE_PER_MINUTE or run fewer workers.',
            default => 'Zoommer does not allow this server to read its catalogue. The importer does not try to get around that: ask Zoommer for API access or to allowlist this server, then run the import again.',
        };

        return "Zoommer refused the request (HTTP {$status}). {$reason}";
    }

    protected function isChallenge(string $body): bool
    {
        return $body !== ''
            && ! str_starts_with(ltrim($body), '{')
            && (bool) preg_match('/cf-browser-verification|Just a moment|cf_chl|Attention Required/i', $body);
    }
}
