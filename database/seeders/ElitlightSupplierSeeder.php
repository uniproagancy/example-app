<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Services\Import\Drivers\Elitlight\ElitlightDriver;
use Illuminate\Database\Seeder;

class ElitlightSupplierSeeder extends Seeder
{
    public function run(): void
    {
        Supplier::firstOrCreate(['code' => 'elitlight'], [
            'is_active' => false,
            'name' => 'Elit Light',
            'driver' => ElitlightDriver::class,
            'priority' => 80,
            'config' => [
                'timeout' => 30,
                'locale' => 'ka',

                'base_url' => 'https://elitlight.ge',
                'catalogue_path' => 'js/trees.js',
                'cache_minutes' => 60,

                'brand' => 'Elit Light',
                'category' => 'ნაძვის ხეები',

                'default_stock' => 5,

                'columns' => [
                    'key' => 'A',
                    'cost' => 'B',
                ],
            ],

            'markup' => [],
        ]);
    }
}
