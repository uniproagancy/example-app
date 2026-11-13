<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Slide;
use Illuminate\Database\Seeder;

class SlideSeeder extends Seeder
{
    public function run(): void
    {
        Slide::query()->get()->each->delete();

        $categoryIds = Category::query()->whereIn('code', ['apple-iphone', 'gaming', 'tvs'])->pluck('id', 'code');

        collect([
            [
                'title' => ['ka' => 'iPhone 17 Pro — შემდეგი დონე', 'en' => 'iPhone 17 Pro — the next level'],
                'text' => ['ka' => 'A19 Pro ჩიპი, 48 MP სამმაგი კამერა და მთელი დღის ბატარეა. ჩააბარე ძველი ტელეფონი და დაზოგე მეტი.', 'en' => 'A19 Pro chip, 48 MP triple camera and all-day battery. Trade in your old phone and save more.'],
                'button_label' => ['ka' => 'იყიდე ახლავე', 'en' => 'Buy now'],
                'category_id' => $categoryIds['apple-iphone'] ?? null,
                'theme' => 'blue',
                'icon' => 'mobile',
            ],
            [
                'title' => ['ka' => 'ითამაშე მეტი, დაელოდე ნაკლები', 'en' => 'Play more, wait less'],
                'text' => ['ka' => 'კონსოლები, ჯოისტიკები და ყურსასმენები — მიწოდებით მთელ საქართველოში.', 'en' => 'Consoles, controllers and headsets — free next-day delivery.'],
                'button_label' => ['ka' => 'გეიმინგ სეზონი 2026', 'en' => 'Gaming season 2026'],
                'category_id' => $categoryIds['gaming'] ?? null,
                'theme' => 'red',
                'icon' => 'gaming',
            ],
            [
                'title' => ['ka' => 'დიდი ეკრანები, მცირე ფასად', 'en' => 'Big screens, small prices'],
                'text' => ['ka' => 'LG, Samsung და Sony-ს OLED და QLED ტელევიზორები, უფასო მონტაჟით თბილისში.', 'en' => 'OLED and QLED TVs from LG, Samsung and Sony, with free installation in Tbilisi.'],
                'button_label' => ['ka' => 'ტელევიზორები −25%', 'en' => 'TVs −25%'],
                'category_id' => $categoryIds['tvs'] ?? null,
                'theme' => 'indigo',
                'icon' => 'tv',
            ],
        ])->each(fn (array $slide, int $index) => Slide::query()->create([...$slide, 'position' => $index + 1]));
    }
}
