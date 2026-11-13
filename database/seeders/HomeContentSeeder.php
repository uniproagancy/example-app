<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Deal;
use App\Models\HomeBlock;
use App\Models\Perk;
use App\Models\Product;
use App\Models\Promo;
use Illuminate\Database\Seeder;

class HomeContentSeeder extends Seeder
{
    public function run(): void
    {
        HomeBlock::query()->get()->each->delete();
        Perk::query()->get()->each->delete();
        Promo::query()->get()->each->delete();
        Deal::query()->get()->each->delete();

        collect([
            ['key' => 'perks'],
            ['key' => 'sale'],
            ['key' => 'deal'],
            ['key' => 'categories'],
            ['key' => 'promos'],
            [
                'key' => 'brands',
                'title' => ['ka' => 'ბრენდები', 'en' => 'Brands'],
            ],
        ])->each(fn (array $block, int $index) => HomeBlock::query()->create([...$block, 'position' => $index + 1]));

        collect([
            ['icon' => 'delivery', 'title' => ['ka' => 'მიწოდება მთელ საქართველოში', 'en' => 'Delivery across Georgia'], 'text' => ['ka' => 'თბილისში — 2 სამუშაო დღეში', 'en' => 'Tbilisi — within 2 business days']],
            ['icon' => 'store', 'title' => ['ka' => 'მიწოდება რეგიონებში', 'en' => 'Delivery to the regions'], 'text' => ['ka' => '3–7 სამუშაო დღეში', 'en' => 'Within 3–7 business days']],
            ['icon' => 'warranty', 'title' => ['ka' => 'ოფიციალური გარანტია', 'en' => 'Official warranty'], 'text' => ['ka' => '2 წლამდე ყველა მოწყობილობაზე', 'en' => 'Up to 2 years on every device']],
        ])->each(fn (array $perk, int $index) => Perk::query()->create([...$perk, 'position' => $index + 1]));

        $categoryIds = Category::query()->whereIn('code', ['headphones', 'laptops', 'smart'])->pluck('id', 'code');

        collect([
            [
                'badge' => ['ka' => '−30%', 'en' => '−30%'],
                'title' => ['ka' => 'აუდიო კვირეული', 'en' => 'Audio week'],
                'text' => ['ka' => 'Sony, JBL და Apple ყურსასმენები და დინამიკები.', 'en' => 'Sony, JBL and Apple headphones and speakers.'],
                'note' => ['ka' => '30 ნოემბრამდე', 'en' => 'Until 30 November'],
                'category_id' => $categoryIds['headphones'] ?? null,
                'theme' => 'navy',
                'icon' => 'headphones',
            ],
            [
                'badge' => ['ka' => '−20%', 'en' => '−20%'],
                'title' => ['ka' => 'სმარტ სახლის კვირა', 'en' => 'Smart home week'],
                'text' => ['ka' => 'რობოტი მტვერსასრუტები, განათება და კამერები.', 'en' => 'Robot vacuums, lighting and cameras.'],
                'note' => ['ka' => '3 დეკემბრამდე', 'en' => 'Until 3 December'],
                'category_id' => $categoryIds['smart'] ?? null,
                'theme' => 'field',
            ],
        ])->each(fn (array $promo, int $index) => Promo::query()->create([...$promo, 'position' => $index + 1]));

        Brand::query()->update(['show_on_home' => false, 'home_position' => 0]);

        collect(['Apple', 'Samsung', 'Sony', 'Xiaomi', 'DJI', 'Logitech', 'JBL', 'Bose'])
            ->each(fn (string $name, int $index) => Brand::query()->where('name', $name)->first()?->update(['show_on_home' => true, 'home_position' => $index + 1]));

        $tablet = Product::query()->where('sku', 'gtab-s10fe')->first();

        if ($tablet) {
            Deal::query()->create([
                'product_id' => $tablet->id,
                'price' => 1099,
                'text' => [
                    'ka' => '10.9″ ეკრანი, S Pen კომპლექტში, IP68 დაცვა. მხოლოდ დღეს — მარაგის ამოწურვამდე.',
                    'en' => '10.9″ display, S Pen in the box, IP68 protection. Today only — while stocks last.',
                ],
                'starts_at' => now()->startOfDay(),
                'ends_at' => now()->addDays(30)->endOfDay(),
            ]);
        }
    }
}
