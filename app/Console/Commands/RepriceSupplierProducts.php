<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Services\Import\ProductImporter;
use App\Support\Catalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('products:reprice {supplier : the supplier code, e.g. zoommer}')]
#[Description('Recalculate the selling prices of a supplier\'s products with its current markup')]
class RepriceSupplierProducts extends Command
{
    public function handle(ProductImporter $importer): int
    {
        $supplier = Supplier::query()->where('code', $this->argument('supplier'))->first();

        if (! $supplier) {
            $this->error("No supplier with code [{$this->argument('supplier')}].");

            return self::FAILURE;
        }

        $tiers = $supplier->markupTiers();
        $this->line($tiers === [] ? 'No markup: prices follow the supplier as they are.' : 'Markup: '.json_encode($tiers));

        $changed = 0;
        $total = 0;

        Product::query()
            ->whereHas('offers', fn ($query) => $query->where('supplier_id', $supplier->id))
            ->chunkById(200, function ($products) use ($importer, &$changed, &$total): void {
                foreach ($products as $product) {
                    $before = [$product->price, $product->old_price];
                    $importer->refreshFromOffers($product);
                    $total++;

                    if ($before !== [$product->price, $product->old_price]) {
                        $changed++;
                    }
                }
            });

        Catalog::flush();
        $this->info("Checked {$total} products, {$changed} prices changed.");

        return self::SUCCESS;
    }
}
