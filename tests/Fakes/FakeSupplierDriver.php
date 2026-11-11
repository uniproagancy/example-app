<?php

namespace Tests\Fakes;

use App\Models\Supplier;
use App\Services\Import\ProductPayload;
use App\Services\Import\SupplierDriver;

class FakeSupplierDriver implements SupplierDriver
{
    public static array $payloads = [];

    public function __construct(public Supplier $supplier) {}

    public function ids(): iterable
    {
        return array_keys(static::$payloads[$this->supplier->code] ?? []);
    }

    public function fetch(string $externalId): ?ProductPayload
    {
        $data = static::$payloads[$this->supplier->code][$externalId] ?? null;

        return $data ? new ProductPayload(...$data) : null;
    }

    public static function payload(string $externalId, array $overrides = []): array
    {
        return array_replace([
            'externalId' => $externalId,
            'sku' => 'SKU-'.$externalId,
            'costPrice' => 100.0,
            'oldCostPrice' => null,
            'stock' => 5,
            'brandName' => 'Acme',
            'categoryName' => 'Phones > Smartphones',
            'translations' => [
                'ka' => ['name' => 'სატესტო ტელეფონი '.$externalId, 'summary' => 'მოკლე აღწერა', 'description' => 'სრული აღწერა'],
                'en' => ['name' => 'Test phone '.$externalId],
            ],
            'specs' => [
                ['name' => 'შიდა მეხსიერება', 'value' => '256 GB', 'locale' => 'ka', 'filterable' => true, 'key' => true],
                ['name' => 'შიდა მეხსიერება', 'value' => '256 GB', 'locale' => 'en', 'filterable' => true, 'key' => true],
                ['name' => 'ფერი', 'value' => 'შავი', 'locale' => 'ka', 'filterable' => false],
            ],
            'images' => [],
        ], $overrides);
    }
}
