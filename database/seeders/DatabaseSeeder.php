<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            MenuItemSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            SupplierSeeder::class,
            PaymentMethodSeeder::class,
            PageSeeder::class,
            SlideSeeder::class,
            HomeContentSeeder::class,
            FaqSeeder::class,
            PromoCodeSeeder::class,
            LocalTestUserSeeder::class,
        ]);
    }
}
