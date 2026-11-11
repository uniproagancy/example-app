<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AltaSupplierSeeder::class,
            AllmarketSupplierSeeder::class,
            AlneoSupplierSeeder::class,
            CitrusSupplierSeeder::class,
            ComfoSupplierSeeder::class,
            EliteSupplierSeeder::class,
            ElitlightSupplierSeeder::class,
            IngcoSupplierSeeder::class,
            KontaktSupplierSeeder::class,
            MagiToysSupplierSeeder::class,
            MetromartSupplierSeeder::class,
            MideaSupplierSeeder::class,
            ThermocenterSupplierSeeder::class,
            ZoommerSupplierSeeder::class,
        ]);
    }
}
