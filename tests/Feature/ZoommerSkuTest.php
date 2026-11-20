<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Supplier;
use App\Services\Import\Drivers\Zoommer\ZoommerDriver;
use App\Services\Import\ProductImporter;
use App\Services\Import\ProductPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeSupplierDriver;
use Tests\TestCase;

class ZoommerSkuTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function sku(string $externalId, ?string $barcode): string
    {
        $driver = new ZoommerDriver((new Supplier)->forceFill(['code' => 'zoommer', 'config' => []]));

        return (fn () => $this->sku($externalId, ['barCode' => $barcode]))->call($driver);
    }

    public function test_the_sku_is_the_digits_of_the_zoommer_barcode(): void
    {
        $this->assertSame('040771', $this->sku('54835', 'ZOOM-040771'));
        $this->assertSame('8806095467092', $this->sku('1', '8806095467092'));
        $this->assertSame('ZOOM-2', $this->sku('2', null));
    }

    public function test_colours_sharing_a_barcode_are_kept_apart_and_never_merged_with_another_supplier(): void
    {
        Storage::fake('public');
        $zoommer = tap(Supplier::query()->where('code', 'zoommer')->sole())->update(['is_active' => true, 'config' => []]);
        $other = Supplier::query()->create(['code' => 'other', 'name' => 'Other', 'driver' => FakeSupplierDriver::class, 'is_active' => true, 'priority' => 20, 'config' => [], 'markup' => []]);
        $importer = app(ProductImporter::class);
        $payload = fn (string $id, string $sku) => new ProductPayload(...FakeSupplierDriver::payload($id, ['sku' => $sku]));

        $foreign = $importer->import($other, $payload('A1', '040771'));
        $glacier = $importer->import($zoommer, $payload('54835', '040771'));
        $silver = $importer->import($zoommer, $payload('54836', '040771'));

        $this->assertNotSame($foreign->id, $glacier->id);
        $this->assertNotSame($glacier->id, $silver->id);
        $this->assertSame(['040771', '040771-54835', '040771-54836'], Product::query()->whereIn('id', [$foreign->id, $glacier->id, $silver->id])->orderBy('id')->pluck('sku')->all());
        $this->assertSame($glacier->id, $importer->import($zoommer, $payload('54835', '040771'))->id);
    }
}
