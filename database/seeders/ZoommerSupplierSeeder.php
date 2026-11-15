<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Services\Import\Drivers\Zoommer\ZoommerDriver;
use Illuminate\Database\Seeder;

class ZoommerSupplierSeeder extends Seeder
{
    public function run(): void
    {
        Supplier::firstOrCreate(['code' => 'zoommer'], [
            'is_active' => false,
            'name' => 'Zoommer',
            'driver' => ZoommerDriver::class,
            'priority' => 10,
            'config' => [
                'from' => 1,
                'to' => 1000,
                'locales' => ['ka', 'en'],
                'stock_cities' => ['თბილისი', 'Tbilisi'],
                'stock_units' => 5,

                'require_stock' => true,
            ],
            'markup' => [],
        ]);
    }
}
