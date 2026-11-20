<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Supplier;
use App\Services\Import\ProductImporter;
use App\Services\Import\ProductPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeSupplierDriver;
use Tests\TestCase;

class SupplierMarkupTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function supplier(array $config = []): Supplier
    {
        return Supplier::query()->create(['code' => 'marked', 'name' => 'Marked', 'driver' => FakeSupplierDriver::class, 'is_active' => true, 'priority' => 10, 'config' => $config, 'markup' => []]);
    }

    public function test_fixed_tiers_add_fifty_up_to_500_and_a_hundred_above(): void
    {
        $supplier = $this->supplier(['markup' => [['add' => 100], ['up_to' => 500, 'add' => 50]]]);

        $this->assertSame(150.0, $supplier->sellingPrice(100));
        $this->assertSame(550.0, $supplier->sellingPrice(500));
        $this->assertSame(600.5, $supplier->sellingPrice(500.5));
        $this->assertSame(1299.0, $supplier->sellingPrice(1199));
        $this->assertSame(1199.0, (new Supplier(['config' => []]))->sellingPrice(1199));
    }

    public function test_imported_prices_and_struck_prices_carry_the_markup_and_reprice_follows_changes(): void
    {
        Storage::fake('public');
        $supplier = $this->supplier(['markup' => [['up_to' => 500, 'add' => 50], ['add' => 100]]]);
        $importer = app(ProductImporter::class);

        $cheap = $importer->import($supplier, new ProductPayload(...FakeSupplierDriver::payload('1', ['costPrice' => 400.0])));
        $dear = $importer->import($supplier, new ProductPayload(...FakeSupplierDriver::payload('2', ['costPrice' => 900.0, 'oldCostPrice' => 1000.0])));

        $this->assertSame('450.00', $cheap->fresh()->price);
        $this->assertSame('400.00', $cheap->fresh()->cost_price);
        $this->assertSame('1000.00', $dear->fresh()->price);
        $this->assertSame('1100.00', $dear->fresh()->old_price);

        $supplier->update(['config' => ['markup' => [['add' => 10]]]]);

        $this->artisan('products:reprice', ['supplier' => 'marked'])->expectsOutputToContain('2 prices changed')->assertSuccessful();

        $this->assertSame('410.00', $cheap->fresh()->price);
        $this->assertSame('910.00', $dear->fresh()->price);

        $dear->update(['price_lock' => true, 'price' => 999]);
        $this->artisan('products:reprice', ['supplier' => 'marked'])->assertSuccessful();
        $this->assertSame('999.00', Product::query()->find($dear->id)->price);
    }
}
