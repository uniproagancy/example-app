<?php

namespace App\Services\Import;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierCategoryMap;
use RuntimeException;

class SupplierActions
{
    public function __construct(
        protected ImportManager $manager,
        protected PriceListLoader $loader,
    ) {}

    public static function drivers(): array
    {
        return collect(glob(app_path('Services/Import/Drivers/*/*Driver.php')) ?: [])
            ->mapWithKeys(function (string $path): array {
                $folder = basename(dirname($path));
                $class = 'App\\Services\\Import\\Drivers\\'.$folder.'\\'.basename($path, '.php');

                return class_exists($class) && is_subclass_of($class, SupplierDriver::class) ? [$class => $folder] : [];
            })
            ->sort()
            ->all();
    }

    public function run(Supplier $supplier, bool $fullScan = false, ?array $only = null): int
    {
        if (! $supplier->is_active) {
            throw new RuntimeException(__('admin.suppliers.inactive'));
        }

        if ($fullScan) {
            $supplier->config = array_merge($supplier->config ?? [], ['full_scan' => true]);
        }

        return $this->manager->run($supplier, $only);
    }

    public static function takesPriceLists(Supplier $supplier): bool
    {
        return filled($supplier->config['columns']['key'] ?? null);
    }

    public function loadPriceList(
        Supplier $supplier,
        string $path,
        int $sheet = 1,
        int $skipRows = 1,
        bool $merge = false,
        ?string $format = null,
    ): array {
        if (! static::takesPriceLists($supplier)) {
            throw new RuntimeException(__('admin.suppliers.no_columns'));
        }

        return $this->loader->load(
            $supplier,
            $path,
            max(1, $sheet),
            max(0, $skipRows),
            $merge ? PriceListLoader::MODE_MERGE : PriceListLoader::MODE_REPLACE,
            $format,
        );
    }

    public function previewPriceList(Supplier $supplier, string $path, int $sheet = 1, int $skipRows = 1, ?string $format = null): array
    {
        if (! static::takesPriceLists($supplier)) {
            throw new RuntimeException(__('admin.suppliers.no_columns'));
        }

        return $this->loader->preview($supplier, $path, max(1, $sheet), max(0, $skipRows), $format);
    }

    public function refreshStock(Supplier $supplier): int
    {
        $driver = $this->manager->driver($supplier);

        if (! $driver instanceof HasStockFeed) {
            throw new RuntimeException(__('admin.suppliers.no_stock_feed'));
        }

        return $driver->refreshStock();
    }

    public function relink(?int $supplierId = null): int
    {
        return SupplierCategoryMap::query()
            ->whereNotNull('category_id')
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->get()
            ->sum(fn (SupplierCategoryMap $map) => $this->relinkMap($map));
    }

    public function relinkMap(SupplierCategoryMap $map): int
    {
        if (! $map->category_id) {
            return 0;
        }

        $products = Product::query()
            ->whereNull('category_id')
            ->where('taxonomy_lock', false)
            ->whereHas('offers', fn ($offers) => $offers
                ->where('supplier_id', $map->supplier_id)
                ->where('external_category', $map->external_name))
            ->get();

        $products->each(fn (Product $product) => $product->update(['category_id' => $map->category_id]));

        return $products->count();
    }
}
