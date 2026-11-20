<?php

namespace App\Services\Import\Drivers\Zoommer;

use App\Models\Supplier;
use App\Services\Import\ImageDownloader;
use App\Services\Import\ProductPayload;
use Illuminate\Validation\ValidationException;

class ZoommerJson
{
    public function __construct(protected Supplier $supplier) {}

    /**
     * @return array{payload: ?ProductPayload, errors: list<string>, seen: array<string, mixed>}
     */
    public function read(string $json): array
    {
        $data = json_decode(trim($json), true);

        if (! is_array($data)) {
            return $this->no(__('admin.import.json_not_json'));
        }

        $product = $data['product'] ?? null;

        if (! is_array($product) || ! isset($product['id']) || ! ctype_digit((string) $product['id'])) {
            return $this->no(__('admin.import.json_no_product'));
        }

        $payload = (new ZoommerDriver($this->supplier))->payloadFromAnswer($data);
        $name = trim((string) ($payload->translations['ka']['name'] ?? ''));
        $errors = [];

        if ($name === '') {
            $errors[] = __('admin.import.json_no_name');
        }

        if ($payload->costPrice <= 0) {
            $errors[] = __('admin.import.json_no_price');
        }

        $seen = [
            'code' => $payload->externalId,
            'sku' => $payload->sku,
            'name' => $name,
            'category' => trim((string) ($product['subCategoryName'] ?? $product['categoryName'] ?? '')) ?: null,
            'price' => $payload->costPrice,
            'stock' => $payload->stock,
            'images' => $payload->images,
        ];

        return ['payload' => $errors === [] ? $payload : null, 'errors' => $errors, 'seen' => $seen];
    }

    /**
     * @param  list<string>  $urls
     * @return array<string, string>|null
     */
    public function assets(string $json, array $urls): ?array
    {
        $data = json_decode($json, true);

        if (! is_array($data) || ! array_key_exists('iapiAssets', $data)) {
            return null;
        }

        $assets = $data['iapiAssets'];

        if (! is_array($assets) || ! array_is_list($assets) || count($assets) > ImageDownloader::MAX_IMAGES) {
            $this->invalid();
        }

        $bytes = [];

        foreach ($assets as $asset) {
            if (! is_array($asset) || ! is_string($asset['url'] ?? null) || ! is_string($asset['base64'] ?? null)
                || ! in_array($asset['url'], $urls, true)) {
                $this->invalid();
            }

            $raw = base64_decode($asset['base64'], true);

            if ($raw === false || $raw === '' || strlen($raw) > (int) config('import.max_image_bytes')) {
                $this->invalid();
            }

            $bytes[$asset['url']] = $raw;
        }

        return $bytes;
    }

    protected function invalid(): never
    {
        throw ValidationException::withMessages(['iapiAssets' => __('admin.import.zoommer_invalid_export')]);
    }

    /**
     * @return array{payload: null, errors: list<string>, seen: array<string, mixed>}
     */
    protected function no(string $why): array
    {
        return ['payload' => null, 'errors' => [$why], 'seen' => []];
    }
}
