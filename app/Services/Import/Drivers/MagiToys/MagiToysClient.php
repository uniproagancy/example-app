<?php

namespace App\Services\Import\Drivers\MagiToys;

use App\Support\Figure;
use App\Support\Redact;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MagiToysClient
{
    public function __construct(protected array $config = []) {}

    public ?string $reason = null;

    public function product(string $url): ?array
    {
        $this->reason = null;

        $body = $this->download($url);

        if ($body === null) {
            return null;
        }

        $data = $this->schema($body);

        if (! $data || trim((string) ($data['name'] ?? '')) === '') {
            $this->reason = 'no product block';

            Log::channel('import')->warning('magitoys page carries no product block', [
                'url' => Redact::secrets($url),
            ]);

            return null;
        }

        $description = $this->description($body);

        return [
            'id' => trim((string) ($data['productID'] ?? '')) ?: null,
            'name' => $this->clean((string) $data['name']),
            'model' => trim((string) ($data['model'] ?? '')) ?: null,
            'brand' => $this->brand($data),
            'category' => $this->clean((string) ($data['category'] ?? '')) ?: null,
            'summary' => $description['summary'],
            'description' => $description['description'],
            'specs' => $description['specs'],
            'price' => $this->price($data),
            'in_stock' => $this->inStock($data),
            'images' => $this->images($body, $data),
            'weight' => $this->weight($description['specs']),
        ];
    }

    protected function download(string $url): ?string
    {
        try {
            $response = Http::timeout((int) ($this->config['timeout'] ?? 25))
                ->connectTimeout((int) ($this->config['connect_timeout'] ?? 10))
                ->retry(1, 400, throw: false)
                ->withHeaders(['User-Agent' => config('import.user_agent')])
                ->get($url);
        } catch (\Throwable $e) {
            $this->reason = 'unreachable';

            Log::channel('import')->warning('magitoys page unreachable', [
                'url' => Redact::secrets($url),
                'error' => Redact::secrets($e->getMessage()),
            ]);

            return null;
        }

        if (! $response->successful()) {
            $this->reason = 'http '.$response->status();

            Log::channel('import')->warning('magitoys page refused', [
                'url' => Redact::secrets($url),
                'status' => $response->status(),
            ]);

            return null;
        }

        return $response->body();
    }

    protected function schema(string $body): ?array
    {
        preg_match_all('~<script[^>]+application/ld\+json[^>]*>(.*?)</script>~si', $body, $blocks);

        foreach ($blocks[1] ?? [] as $block) {
            $data = json_decode(html_entity_decode(trim($block), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);

            if (is_array($data) && ($data['@type'] ?? null) === 'Product') {
                return $data;
            }
        }

        return null;
    }

    protected function brand(array $data): ?string
    {
        $brand = $data['brand'] ?? null;

        if (is_array($brand)) {
            $brand = $brand['name'] ?? null;
        }

        $brand = trim((string) ($brand ?: $data['manufacturer'] ?? ''));

        return $brand !== '' ? $this->clean($brand) : null;
    }

    protected function price(array $data): ?float
    {
        $price = $data['offers']['price'] ?? null;

        return Figure::money($price, Figure::DOT);
    }

    protected function inStock(array $data): bool
    {
        $availability = (string) ($data['offers']['availability'] ?? '');

        return $availability === '' || str_contains(strtolower($availability), 'instock');
    }

    protected function description(string $body): array
    {
        $raw = $this->descriptionHtml($body);

        if ($raw === '') {
            return ['summary' => null, 'description' => null, 'specs' => []];
        }

        $lines = preg_split('~<br\s*/?>|<p[^>]*>|</p>|</div>~i', $raw) ?: [];
        $lines = array_values(array_filter(array_map(
            fn (string $line) => $this->clean(strip_tags($line)),
            $lines,
        ), fn (string $line) => $line !== ''));

        $summary = [];
        $specs = [];
        $inSpecs = false;

        foreach ($lines as $line) {
            if (! $inSpecs && $this->isSpecHeading($line)) {
                $inSpecs = true;

                continue;
            }

            if (! $inSpecs) {
                $summary[] = $line;

                continue;
            }

            if ($pair = $this->pair($line)) {
                $specs[] = $pair;
            }
        }

        $prose = implode("\n", $summary);

        return [

            'summary' => $prose !== '' ? Str::limit($prose, 240) : null,
            'description' => $prose !== '' ? $prose : null,
            'specs' => $specs,
        ];
    }

    protected function descriptionHtml(string $body): string
    {

        if (preg_match('~<div[^>]+class="[^"]*ds-product-tab-content-text[^"]*"[^>]*>(.*?)</div>~si', $body, $m)) {
            return $m[1];
        }

        if (preg_match('~<div[^>]+id="tab-description"[^>]*>(.*?)</div>~si', $body, $m)) {
            return $m[1];
        }

        return '';
    }

    protected function isSpecHeading(string $line): bool
    {
        $line = rtrim($line, ": \t");

        return in_array($line, ['მახასიათებლები', 'მახასიათებელი', 'Specifications'], true);
    }

    protected function pair(string $line): ?array
    {
        $parts = preg_split('~\s*:\s*~u', $line, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$name, $value] = array_map(fn ($part) => $this->clean((string) $part), $parts);

        if ($name === '' || $value === '' || mb_strlen($name) > 60 || mb_strlen($value) > 120) {
            return null;
        }

        if (in_array(mb_strtolower($name), ['ბრენდი', 'brand'], true)) {
            return null;
        }

        return ['name' => $name, 'value' => $value];
    }

    protected function images(string $body, array $data): array
    {
        $first = $this->original((string) ($data['image'] ?? ''));

        $folder = preg_match('~/image/catalog/(.*/)[^/]+$~', $first, $m) ? $m[1] : null;

        $found = $first !== '' ? [$first] : [];

        if ($folder === null) {
            return $found;
        }

        $pattern = '~https?://[^\s"\'<>]+/image/(?:cache/)?catalog/'
            .preg_quote($folder, '~')
            .'[^\s"\'<>]+?\.(?:jpe?g|png|webp)~i';

        preg_match_all($pattern, $body, $matches);

        foreach ($matches[0] ?? [] as $url) {
            $url = $this->original(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ($url !== '' && ! in_array($url, $found, true)) {
                $found[] = $url;
            }
        }

        return array_slice($found, 0, (int) ($this->config['max_images'] ?? 6));
    }

    protected function original(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $url = preg_replace('~/image/cache/~', '/image/', $url);

        return (string) preg_replace('~-\d{2,4}x\d{2,4}(\.[a-z]+)$~i', '$1', (string) $url);
    }

    protected function weight(array $specs): ?int
    {
        foreach ($specs as $spec) {
            if (! str_contains(mb_strtolower($spec['name']), 'წონა')) {
                continue;
            }

            if (preg_match('~([\d.,]+)~', $spec['value'], $m)) {
                $grams = (int) round(((float) str_replace(',', '.', $m[1])) * 1000);

                return $grams > 0 ? $grams : null;
            }
        }

        return null;
    }

    protected function clean(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace("\u{00A0}", ' ', $value);

        return trim((string) preg_replace('~\s+~u', ' ', $value));
    }
}
