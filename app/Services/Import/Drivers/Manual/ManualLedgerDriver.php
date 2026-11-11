<?php

namespace App\Services\Import\Drivers\Manual;

use App\Models\ProductOffer;
use App\Models\Supplier;
use App\Models\SupplierStock;
use App\Services\Import\ChecksImportSource;
use App\Services\Import\ProductPayload;
use App\Services\Import\SupplierDriver;
use RuntimeException;

class ManualLedgerDriver implements ChecksImportSource, SupplierDriver
{
    public const PROFILE = 'christmas-trees-1-v1';

    private const HEIGHTS = [
        'E' => [150, 180, 210, 240],
        'C' => [180, 210, 240, 270],
        'D' => [180, 210, 240, 270],
        'N' => [180, 210, 240, 270],
        'K' => [180, 210, 240],
        'Z' => [180, 210, 240],
        'F' => [120, 150, 180, 210, 240, 270],
        'B' => [180, 210, 240, 270],
        'i' => [180, 210, 240, 270],
        'S' => [180, 210, 240, 270],
        'G' => [180, 210, 240, 270],
    ];

    public function __construct(public Supplier $supplier) {}

    public function checkSource(): void
    {
        if ($this->supplier->code !== 'christmas-trees-1' || (int) $this->supplier->id < 1) {
            throw new RuntimeException('The manual catalogue is not bound to this supplier.');
        }
    }

    public function ids(): iterable
    {
        $this->checkSource();

        return [];
    }

    public function fetch(string $externalId): ?ProductPayload
    {
        $this->checkSource();
        $row = $this->rows()->where('external_id', $externalId)->first();

        if (! $row || $this->identity($row) !== $externalId) {
            return null;
        }

        $offer = ProductOffer::where('supplier_id', $this->supplier->id)
            ->where('external_id', $externalId)
            ->with('product')
            ->first();
        $product = $offer?->product;

        if (! $product || (string) $offer->external_id !== $externalId
            || (string) $product->sku !== $externalId || $product->deleted_at !== null
            || (int) $product->category_id !== 410 || ! $product->taxonomy_lock) {
            return null;
        }

        $cost = (float) $row->cost_price;

        if (! is_finite($cost) || $cost <= 0) {
            return null;
        }

        return new ProductPayload(
            externalId: $externalId,
            sku: (string) $product->sku,
            costPrice: $cost,
            oldCostPrice: null,

            stock: min(max(0, (int) $row->quantity), max(0, (int) $product->stock)),
            brandName: $offer->external_brand,
            categoryName: $offer->external_category,
            translations: [],
            specs: [],
            images: [],
            variantGroup: $product->variant_group,
            isPreorder: (bool) $product->is_preorder,
            releaseDate: $product->release_date?->format('Y-m-d'),
        );
    }

    protected function rows()
    {
        return SupplierStock::where('supplier_id', $this->supplier->id)
            ->where('external_id', 'like', 'TREE1-%');
    }

    protected function identity(SupplierStock $row): ?string
    {
        $data = $row->data;

        if (! is_array($data) || ($data['profile'] ?? null) !== self::PROFILE
            || ! is_string($data['model'] ?? null) || ! is_int($data['height_cm'] ?? null)
            || ! in_array($data['height_cm'], self::HEIGHTS[$data['model']] ?? [], true)) {
            return null;
        }

        $expected = 'TREE1-'.$data['model'].'-'.$data['height_cm'];

        return (string) $row->external_id === $expected ? $expected : null;
    }
}
