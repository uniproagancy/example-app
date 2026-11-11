<?php

namespace App\Services\Import\Drivers\Kontakt;

use App\Models\ProductOffer;
use App\Models\Supplier;
use App\Models\SupplierStock;
use App\Services\Import\ProductPayload;
use App\Services\Import\SupplierDriver;
use App\Support\Figure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

class KontaktDriver implements SupplierDriver
{
    protected KontaktClient $client;

    public function __construct(public Supplier $supplier)
    {
        $this->client = new KontaktClient($supplier->config ?? []);
    }

    public function ids(): iterable
    {
        return SupplierStock::where('supplier_id', $this->supplier->id)

            ->lazyById(500)
            ->map(fn (SupplierStock $s) => $s->external_id);
    }

    public function fetch(string $externalId): ?ProductPayload
    {
        $row = SupplierStock::where('supplier_id', $this->supplier->id)
            ->where('external_id', $externalId)
            ->first();

        if (! $row) {
            return null;
        }

        $data = $row->data ?? [];
        $url = $data['url'] ?? null;

        if (! $url || ! str_starts_with($url, 'http')) {
            return null;
        }

        try {
            $page = $this->client->page($url);
        } catch (ConnectionException $e) {
            $page = null;
        }

        if ($page && $page['name']) {
            return $this->toPayload($externalId, $row, $data, $page);
        }

        return $this->fromSheetAlone($externalId, $row, $data);
    }

    protected function fromSheetAlone(string $externalId, SupplierStock $row, array $data): ?ProductPayload
    {
        $offer = ProductOffer::where('supplier_id', $this->supplier->id)
            ->where('external_id', $externalId)
            ->with('product')
            ->first();

        if (! $offer?->product) {
            return null;
        }

        [$price, $old] = $this->prices($row, $data, 0.0);

        if ($price <= 0) {
            return null;
        }

        Log::channel('import')->info('kontakt page unavailable, price taken from the sheet', [
            'external' => $externalId,
            'product' => $offer->product_id,
            'url' => $data['url'] ?? null,
        ]);

        return new ProductPayload(
            externalId: $externalId,
            sku: $offer->product->sku,
            costPrice: $price,
            oldCostPrice: $old,
            stock: max(0, (int) $row->quantity),
            brandName: null,
            categoryName: null,
            translations: [],
        );
    }

    protected function prices(SupplierStock $row, array $data, float $fromPage): array
    {
        $cost = (float) $row->cost_price ?: $fromPage;

        if ($cost <= 0) {
            return [0.0, null];
        }

        $sale = Figure::money($data['sale'] ?? null, $this->supplier->config['decimal'] ?? null) ?? 0.0;

        return $sale > 0 && $sale < $cost ? [$sale, $cost] : [$cost, null];
    }

    protected function toPayload(string $model, SupplierStock $row, array $data, array $page): ?ProductPayload
    {
        $locale = $this->supplier->config['locale'] ?? 'ka';

        [$price, $old] = $this->prices($row, $data, (float) $page['price']);

        if ($price <= 0) {
            return null;
        }

        $specs = array_map(fn ($spec) => [
            'name' => $spec['name'],
            'value' => $spec['value'],
            'locale' => $locale,
            'key' => false,

            'filterable' => false,
            'color' => null,
        ], $page['specs'] ?? []);

        $stock = $page['in_stock'] ? max(1, (int) $row->quantity) : 0;

        return new ProductPayload(
            externalId: $model,

            sku: $this->supplier->code.'-'.($page['sku'] ?: $model),
            costPrice: $price,
            oldCostPrice: $old,
            stock: $stock,
            brandName: $page['brand'] ?? null,

            categoryName: $data['category'] ?? $page['category'] ?? null,
            translations: [$locale => [
                'name' => $this->rewrite($page['name']),
                'description' => $this->rewrite($page['description']),
            ]],
            specs: $specs,
            images: $page['images'] ?? [],
        );
    }

    protected function rewrite(?string $text): ?string
    {
        if (! $text) {
            return $text;
        }

        foreach ((array) ($this->supplier->config['replace'] ?? []) as $from => $to) {
            $text = preg_replace('/'.preg_quote($from, '/').'/iu', $to, $text);
        }

        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
