<?php

namespace App\Services\Import\Drivers\Elite;

use Illuminate\Support\Facades\Http;

class EliteClient
{
    protected const BLOCKED_STATUSES = [401, 403, 429, 503];

    public function __construct(protected array $config = []) {}

    public function product(string $externalId): ?array
    {
        $url = $this->url();

        if ($url === '') {
            throw new BlockedByElite('ELITE_API_URL is not set. Elite has to provide an API address (and token) for this shop before its catalogue can be imported.');
        }

        $response = Http::timeout($this->config['timeout'] ?? config('import.request_timeout'))
            ->retry(2, 500, throw: false)
            ->withHeaders(array_filter([
                'Accept' => 'application/json',
                'User-Agent' => (string) config('import.user_agent'),
            ]))
            ->get($url, array_filter([
                'type' => 'product',
                'productId' => $externalId,
                'token' => config('services.elite.token'),
            ]));

        if (in_array($response->status(), self::BLOCKED_STATUSES, true)) {
            throw new BlockedByElite($this->refusal($response->status()));
        }

        $data = $response->json();

        if (isset($data['error'])) {
            throw new BlockedByElite('Elite proxy rejected the request: '.$data['error']);
        }

        if (! $response->successful()) {
            throw new BlockedByElite('Elite proxy answered HTTP '.$response->status().'.');
        }

        return empty($data['product']) ? null : [
            'product' => $data['product'],
            'availabilityInStores' => $data['availabilityInStores'] ?? [],
        ];
    }

    public function imageUrl(string $url): string
    {
        $proxy = $this->url();

        if ($proxy === '') {
            return $url;
        }

        return $proxy.'?'.http_build_query(array_filter([
            'type' => 'image',
            'url' => $url,
            'token' => config('services.elite.token'),
        ]));
    }

    protected function url(): string
    {
        return rtrim((string) ($this->config['api_url'] ?? config('services.elite.api_url')), '/');
    }

    protected function refusal(int $status): string
    {
        $reason = match ($status) {
            401, 403 => 'ELITE_TOKEN is missing or no longer valid; the proxy checks it on every request. Replace it and run the import again.',
            429 => 'Too many requests. Lower IMPORT_RATE_PER_MINUTE or run fewer workers.',
            default => 'The Elite proxy is unavailable for now; this is usually temporary.',
        };

        return "Elite refused the request (HTTP {$status}). {$reason}";
    }
}
