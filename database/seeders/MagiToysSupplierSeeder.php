<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Services\Import\Drivers\MagiToys\MagiToysDriver;
use Illuminate\Database\Seeder;

class MagiToysSupplierSeeder extends Seeder
{
    public function run(): void
    {
        Supplier::firstOrCreate(['code' => 'magitoys'], [
            'is_active' => false,
            'name' => 'Magi Toys',
            'driver' => MagiToysDriver::class,
            'priority' => 70,
            'config' => [
                'timeout' => 25,
                'connect_timeout' => 10,
                'locale' => 'ka',

                'max_images' => 6,

                'default_stock' => 0,

                'min_cost' => 70,

                'columns' => [
                    'key' => 'C',
                    'key_as' => 'url',
                    'stock' => 'B',
                    'cost' => 'D',
                    'url' => 'C',
                    'code' => 'A',
                ],

                'key_specs' => ['სიმძლავრე', 'ვოლტაჟი', 'ტიპი', 'მოდელი'],
            ],

            'markup' => [],
        ]);
    }
}
