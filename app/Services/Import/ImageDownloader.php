<?php

namespace App\Services\Import;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Import\Drivers\Alta\AltaDriver;
use App\Services\Import\Drivers\Alta\AltaImageCache;
use App\Support\Redact;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageDownloader
{
    protected const DISK = 'public';

    public const MAX_IMAGES = 8;

    protected const CONNECT_TIMEOUT = 10;

    protected const READ_TIMEOUT = 15;

    protected const RETRIES = 1;

    protected const FAILURES_BEFORE_SKIP = 5;

    protected const MAX_SIDE = 12_000;

    protected const MAX_PIXELS = 40_000_000;

    protected const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

    protected static array $failures = [];

    public function sync(Product $product, array $urls, int $limit = self::MAX_IMAGES): void
    {
        $deadline = microtime(true) + (int) config('import.image_budget', 120);
        $urls = array_values(array_unique(array_filter(array_map(fn ($url) => trim((string) $url), $urls))));
        $hashes = $product->images()->whereNotNull('hash')->pluck('hash')->all();
        $position = (int) $product->images()->max('position');

        foreach (array_slice($urls, 0, $limit) as $index => $url) {
            if (microtime(true) >= $deadline) {
                Log::channel('import')->info('image budget spent', [
                    'product' => $product->id,
                    'fetched' => $index,
                    'of' => min(count($urls), $limit),
                ]);

                break;
            }

            try {
                $stored = $this->store($product, $url, $index);
            } catch (\Throwable $e) {
                Log::channel('import')->warning('image skipped', [
                    'product' => $product->id,
                    'url' => Redact::secrets($url),
                    'error' => Redact::secrets($e->getMessage()),
                ]);

                continue;
            }

            if ($stored === null) {
                continue;
            }

            [$path, $hash] = $stored;

            if (in_array($hash, $hashes, true)) {
                continue;
            }

            $hashes[] = $hash;

            ProductImage::query()->create([
                'product_id' => $product->id,
                'path' => $path,
                'hash' => $hash,
                'position' => ++$position,
            ]);
        }
    }

    protected function store(Product $product, string $url, int $index): ?array
    {
        if (! $this->isFetchable($url)) {
            Log::channel('import')->warning('image address refused', ['product' => $product->id, 'url' => Redact::secrets($url)]);

            return null;
        }

        $cached = $this->altaCached($product, $url);
        $bytes = $cached['bytes'] ?? null;

        if ($bytes === null) {
            if ($this->hostIsDown($url)) {
                return null;
            }

            $bytes = $this->download($product, $url);
        }

        if ($bytes === null) {
            return null;
        }

        $extension = $this->rasterExtension($bytes);

        if ($extension === null) {
            $this->noteFailure($url);
            Log::channel('import')->warning('image response was not a valid picture', ['product' => $product->id, 'url' => Redact::secrets($url)]);

            return null;
        }

        $hash = sha1($bytes);
        $path = sprintf(
            'products/%d/%s-%02d-%s.%s',
            $product->id,
            Str::slug(Str::limit($product->sku, 40, '')) ?: 'p'.$product->id,
            $index + 1,
            substr($hash, 0, 12),
            $extension,
        );

        if (! Storage::disk(self::DISK)->exists($path) && Storage::disk(self::DISK)->put($path, $bytes) === false) {
            Log::channel('import')->error('image could not be written to disk', ['product' => $product->id, 'path' => $path]);

            return null;
        }

        unset(self::$failures[$this->hostOf($url)]);

        return [$path, $hash];
    }

    protected function download(Product $product, string $url): ?string
    {
        try {
            $response = Http::timeout(self::READ_TIMEOUT)
                ->connectTimeout(self::CONNECT_TIMEOUT)
                ->retry(self::RETRIES, 500, throw: false)
                ->withOptions(['force_ip_resolve' => 'v4', 'allow_redirects' => ['max' => 3, 'protocols' => ['http', 'https']]])
                ->withHeaders([
                    'User-Agent' => (string) config('import.user_agent'),
                    'Accept' => 'image/avif,image/webp,image/*;q=0.8',
                ])
                ->get($this->addressOf($url));
        } catch (\Throwable $e) {
            $this->noteFailure($url);
            Log::channel('import')->warning('image download error', [
                'product' => $product->id,
                'url' => Redact::secrets($url),
                'error' => Redact::secrets($e->getMessage()),
            ]);

            return null;
        }

        if (! $response->successful()) {
            $this->noteFailure($url);
            Log::channel('import')->warning('image download failed', [
                'product' => $product->id,
                'url' => Redact::secrets($url),
                'status' => $response->status(),
            ]);

            return null;
        }

        $bytes = $response->body();

        return strlen($bytes) <= (int) config('import.max_image_bytes', 8 * 1024 * 1024) ? $bytes : null;
    }

    protected function altaCached(Product $product, string $url): ?array
    {
        if (! in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), ['imgstore.alta.ge', 'alta.ge'], true)) {
            return null;
        }

        $product->loadMissing('offers.supplier');

        foreach ($product->offers as $offer) {
            if ($offer->supplier?->driver !== AltaDriver::class) {
                continue;
            }

            $config = array_replace((array) config('services.alta', []), $offer->supplier->config ?? []);
            $directory = $config['retail_directory'] ?? storage_path('app/private/alta-retail');

            return $directory ? AltaImageCache::read($directory, $url, (int) ($config['retail_max_age'] ?? 86400)) : null;
        }

        return null;
    }

    protected function rasterExtension(string $bytes): ?string
    {
        $image = @getimagesizefromstring($bytes);

        if (! $image || $image[0] < 1 || $image[1] < 1 || $image[0] > self::MAX_SIDE || $image[1] > self::MAX_SIDE
            || $image[0] * $image[1] > self::MAX_PIXELS) {
            return null;
        }

        return self::EXTENSIONS[$image['mime'] ?? ''] ?? null;
    }

    protected function isFetchable(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = (string) parse_url($url, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    protected function hostIsDown(string $url): bool
    {
        return (self::$failures[$this->hostOf($url)] ?? 0) >= self::FAILURES_BEFORE_SKIP;
    }

    protected function noteFailure(string $url): void
    {
        $host = $this->hostOf($url);
        self::$failures[$host] = (self::$failures[$host] ?? 0) + 1;

        if (self::$failures[$host] === self::FAILURES_BEFORE_SKIP) {
            Log::channel('import')->warning('image host skipped for this run', ['host' => $host]);
        }
    }

    protected function hostOf(string $url): string
    {
        return (string) (parse_url($url, PHP_URL_HOST) ?: 'unknown');
    }

    protected function addressOf(string $url): string
    {
        $parts = parse_url($url);

        if (! isset($parts['path'])) {
            return $url;
        }

        $path = implode('/', array_map(
            fn (string $segment) => rawurlencode(rawurldecode($segment)),
            explode('/', $parts['path']),
        ));

        return ($parts['scheme'] ?? 'https').'://'
            .($parts['host'] ?? '')
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .$path
            .(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
