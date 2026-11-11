<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Services\Import\Drivers\Citrus\CitrusDriver;
use Illuminate\Database\Seeder;

class CitrusSupplierSeeder extends Seeder
{
    public function run(): void
    {
        Supplier::firstOrCreate(['code' => 'citrus'], [
            'is_active' => false,
            'name' => 'Citrus',
            'driver' => CitrusDriver::class,
            'priority' => 70,
            'config' => [
                'timeout' => 30,
                'locale' => 'ka',

                'replace' => [
                    'www.citrus.ge' => 'iapi.ge',
                    'citrus.ge' => 'iapi.ge',
                    'ციტრუსი' => 'iapi.ge',
                ],
            ],

            'markup' => [],
        ]);
    }
}
