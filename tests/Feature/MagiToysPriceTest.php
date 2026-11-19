<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\SupplierStock;
use App\Services\Import\Drivers\MagiToys\MagiToysDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MagiToysPriceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function costFor(float $sheet, string $page): ?float
    {
        $supplier = Supplier::query()->where('code', 'magitoys')->sole();
        SupplierStock::query()->where('supplier_id', $supplier->id)->delete();
        SupplierStock::query()->create([
            'supplier_id' => $supplier->id,
            'external_id' => 'MT-1',
            'quantity' => 3,
            'cost_price' => $sheet,
            'data' => ['url' => 'https://b2m.test/compressor', 'code' => 'MT-1'],
            'synced_at' => now(),
        ]);

        Http::fake(['b2m.test/*' => Http::response('<html><script type="application/ld+json">'.json_encode([
            '@type' => 'Product',
            'name' => 'Compressor',
            'offers' => ['price' => $page, 'availability' => 'https://schema.org/InStock'],
        ]).'</script></html>')]);

        return (new MagiToysDriver($supplier))->fetch('MT-1')?->costPrice;
    }

    public function test_the_sheet_price_is_used_when_it_agrees_with_the_page(): void
    {
        $this->assertSame(1250.0, $this->costFor(1250, '1260.00'));
    }

    public function test_a_sheet_price_far_from_the_page_gives_way_to_the_page(): void
    {
        $this->assertSame(2300.0, $this->costFor(200, '2300.00'));
    }

    public function test_a_sheet_price_blown_up_by_a_misread_cell_gives_way_to_the_page(): void
    {
        $this->assertSame(140.0, $this->costFor(14040, '140.00'));
    }
}
