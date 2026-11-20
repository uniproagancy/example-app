<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Services\Import\Drivers\Zoommer\ZoommerDriver;
use Tests\TestCase;

class ZoommerSkuTest extends TestCase
{
    protected function sku(string $externalId, ?string $barcode): string
    {
        $driver = new ZoommerDriver((new Supplier)->forceFill(['code' => 'zoommer', 'config' => []]));

        return (fn () => $this->sku($externalId, ['barCode' => $barcode]))->call($driver);
    }

    public function test_colours_sharing_a_zoommer_model_code_get_their_own_sku(): void
    {
        $this->assertSame('ZOOM-54835', $this->sku('54835', 'ZOOM-040771'));
        $this->assertSame('ZOOM-54836', $this->sku('54836', 'ZOOM-040771'));
    }

    public function test_a_real_barcode_is_kept_and_a_missing_one_falls_back_to_the_id(): void
    {
        $this->assertSame('8806095467092', $this->sku('1', '8806095467092'));
        $this->assertSame('ZOOM-2', $this->sku('2', null));
    }
}
