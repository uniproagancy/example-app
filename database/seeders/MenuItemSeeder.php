<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuItemSeeder extends Seeder
{
    public function run(): void
    {
        MenuItem::query()->get()->each->delete();

        collect([
            ['header', ['ka' => 'მთავარი გვერდი', 'en' => 'Home'], 'home', null],
            ['header', ['ka' => 'პროდუქცია', 'en' => 'Products'], 'catalog', null],
            ['header', ['ka' => 'სიახლეები', 'en' => 'New arrivals'], null, '#'],
            ['header', ['ka' => 'ბრენდები', 'en' => 'Brands'], 'home', '#brands'],
            ['header', ['ka' => 'განვადება', 'en' => 'Installments'], null, '#'],
            ['header', ['ka' => 'ჩვენ შესახებ', 'en' => 'About us'], 'about', null],
            ['header', ['ka' => 'კონტაქტი', 'en' => 'Contact'], 'contact', null],
            ['header_highlight', ['ka' => 'აქციები', 'en' => 'Promotions'], 'promotions', null],
        ])->each(fn (array $item, int $index) => MenuItem::query()->create([
            'location' => $item[0],
            'title' => $item[1],
            'route_name' => $item[2],
            'url' => $item[3],
            'position' => $index + 1,
        ]));
    }
}
