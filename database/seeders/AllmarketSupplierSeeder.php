<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Services\Import\Drivers\Allmarket\AllmarketDriver;
use Illuminate\Database\Seeder;

class AllmarketSupplierSeeder extends Seeder
{
    public function run(): void
    {
        Supplier::firstOrCreate(['code' => 'allmarket'], [
            'is_active' => false,
            'name' => 'Allmarket',
            'driver' => AllmarketDriver::class,
            'priority' => 50,
            'config' => [
                'timeout' => 60,
                'locale' => 'ka',
            ],
            'markup' => [],
        ]);
    }
}
