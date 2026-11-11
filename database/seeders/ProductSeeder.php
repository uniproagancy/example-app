<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Import\TaxonomyResolver;
use App\Support\Catalog;
use App\Support\Slug;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::query()->get()->each->delete();

        $categories = Category::query()->whereNotNull('code')->pluck('id', 'code');
        $usedSlugs = [];

        foreach ($this->products() as $position => $data) {
            $brand = Brand::query()->firstOrCreate(
                ['slug' => Str::slug($data['brand'])],
                ['name' => $data['brand']],
            );

            $product = Product::query()->create([
                'category_id' => $categories[$data['sub']] ?? $categories[$data['cat']],
                'brand_id' => $brand->id,
                'sku' => $data['id'],
                'supplier_sku' => $data['supplier_sku'] ?? null,
                'price' => $data['price'],
                'old_price' => $data['old'] ?? null,
                'stock' => $data['stock'] ?? 10,
                'is_new' => $data['new'] ?? false,
                'position' => $position + 1,
            ]);

            $englishName = is_array($data['name']) ? $data['name']['en'] : $data['name'];
            $slug = Str::slug($brand->name.' '.$englishName);
            $slug = isset($usedSlugs[$slug]) ? $slug.'-'.$product->id : $slug;
            $usedSlugs[$slug] = true;

            foreach (['ka', 'en'] as $locale) {
                $name = is_array($data['name']) ? $data['name'][$locale] : $data['name'];

                $product->translations()->create([
                    'locale' => $locale,
                    'name' => $name,
                    'slug' => $slug,
                    'subtitle' => $data['subtitle'][$locale] ?? null,
                    'description' => $data['description'][$locale] ?? null,
                ]);
            }

            $specs = $data['specs'] ?? [
                [['ka' => 'ბრენდი', 'en' => 'Brand'], ['ka' => $brand->name, 'en' => $brand->name]],
                [['ka' => 'გარანტია', 'en' => 'Warranty'], ['ka' => '2 წელი', 'en' => '2 years']],
                [['ka' => 'მდგომარეობა', 'en' => 'Condition'], ['ka' => 'ახალი', 'en' => 'New']],
            ];

            foreach ($specs as $specPosition => [$specName, $specValue]) {
                $spec = $product->specs()->create(['attribute_id' => $this->attribute($specName)->id, 'position' => $specPosition + 1]);
                $spec->saveTranslations(collect($specValue)->map(fn (string $value) => ['value' => $value])->all());
            }

            foreach ($data['options'] ?? [] as $optionPosition => $option) {
                $product->options()->create([...$option, 'position' => $optionPosition + 1]);
            }
        }

        Catalog::flush();
    }

    protected array $attributes = [];

    protected function attribute(array $names): Attribute
    {
        $code = TaxonomyResolver::fitTo(Slug::make($names['ka']) ?: Str::slug($names['en'] ?? 'spec'));

        if (isset($this->attributes[$code])) {
            return $this->attributes[$code];
        }

        $attribute = Attribute::query()->firstOrCreate(['code' => $code], ['type' => Attribute::TYPE_SELECT, 'sort_order' => 100]);

        if ($attribute->wasRecentlyCreated) {
            $attribute->saveTranslations(collect($names)->map(fn (string $name) => ['name' => $name])->all());
        }

        return $this->attributes[$code] = $attribute;
    }

    protected function products(): array
    {
        return [
            [
                'id' => 'ip16', 'supplier_sku' => 'MYNF3/A', 'cat' => 'mobile', 'sub' => 'apple-iphone', 'brand' => 'Apple', 'name' => 'iPhone 16 Pro', 'price' => 3899, 'old' => 4199,
                'description' => [
                    'ka' => 'iPhone 16 Pro ტიტანის კორპუსით, A18 Pro ჩიპით და ახალი Camera Control ღილაკით. 48 MP Fusion კამერა 4K120 ვიდეოს იღებს Dolby Vision-ით, ხოლო 6.3″ Super Retina XDR ეკრანი ProMotion ტექნოლოგიით 120 Hz სიხშირეზე მუშაობს.',
                    'en' => 'iPhone 16 Pro with a titanium design, the A18 Pro chip and the new Camera Control. The 48 MP Fusion camera shoots 4K120 Dolby Vision video, and the 6.3″ Super Retina XDR display with ProMotion runs at up to 120 Hz.',
                ],
                'options' => [
                    ['type' => 'color', 'label' => ['ka' => 'Desert Titanium', 'en' => 'Desert Titanium'], 'hex' => '#C8B39B', 'is_default' => true],
                    ['type' => 'color', 'label' => ['ka' => 'Natural Titanium', 'en' => 'Natural Titanium'], 'hex' => '#B9B5AD'],
                    ['type' => 'color', 'label' => ['ka' => 'White Titanium', 'en' => 'White Titanium'], 'hex' => '#EDEBE6'],
                    ['type' => 'color', 'label' => ['ka' => 'Black Titanium', 'en' => 'Black Titanium'], 'hex' => '#3C3C3F'],
                    ['type' => 'storage', 'label' => ['ka' => '128 GB', 'en' => '128 GB'], 'price' => 3499, 'old_price' => 3799],
                    ['type' => 'storage', 'label' => ['ka' => '256 GB', 'en' => '256 GB'], 'price' => 3899, 'old_price' => 4199, 'is_default' => true],
                    ['type' => 'storage', 'label' => ['ka' => '512 GB', 'en' => '512 GB'], 'price' => 4599, 'old_price' => 4899],
                    ['type' => 'storage', 'label' => ['ka' => '1 TB', 'en' => '1 TB'], 'price' => 5299, 'old_price' => 5599],
                ],
                'specs' => [
                    [['ka' => 'მოდელი', 'en' => 'Model'], ['ka' => 'iPhone 16 Pro (A3293)', 'en' => 'iPhone 16 Pro (A3293)']],
                    [['ka' => 'ეკრანი', 'en' => 'Display'], ['ka' => '6.3″ Super Retina XDR OLED', 'en' => '6.3″ Super Retina XDR OLED']],
                    [['ka' => 'რეზოლუცია', 'en' => 'Resolution'], ['ka' => '2622 × 1206, 460 ppi', 'en' => '2622 × 1206, 460 ppi']],
                    [['ka' => 'განახლების სიხშირე', 'en' => 'Refresh rate'], ['ka' => 'ProMotion 1–120 Hz', 'en' => 'ProMotion 1–120 Hz']],
                    [['ka' => 'ჩიპი', 'en' => 'Chip'], ['ka' => 'A18 Pro', 'en' => 'A18 Pro']],
                    [['ka' => 'ოპერაციული სისტემა', 'en' => 'Operating system'], ['ka' => 'iOS 18', 'en' => 'iOS 18']],
                    [['ka' => 'მთავარი კამერა', 'en' => 'Main camera'], ['ka' => '48 MP Fusion, ƒ/1.78', 'en' => '48 MP Fusion, ƒ/1.78']],
                    [['ka' => 'ულტრაფართო კამერა', 'en' => 'Ultra wide camera'], ['ka' => '48 MP, ƒ/2.2', 'en' => '48 MP, ƒ/2.2']],
                    [['ka' => 'ტელეფოტო', 'en' => 'Telephoto'], ['ka' => '12 MP, 5× ოპტიკური ზუმი', 'en' => '12 MP, 5× optical zoom']],
                    [['ka' => 'ვიდეო', 'en' => 'Video'], ['ka' => '4K 120 fps, Dolby Vision', 'en' => '4K 120 fps, Dolby Vision']],
                    [['ka' => 'ფრონტალური კამერა', 'en' => 'Front camera'], ['ka' => '12 MP TrueDepth', 'en' => '12 MP TrueDepth']],
                    [['ka' => 'კორპუსი', 'en' => 'Body'], ['ka' => 'ტიტანი, Ceramic Shield', 'en' => 'Titanium, Ceramic Shield']],
                    [['ka' => 'წყალგამძლეობა', 'en' => 'Water resistance'], ['ka' => 'IP68', 'en' => 'IP68']],
                    [['ka' => 'წონა', 'en' => 'Weight'], ['ka' => '199 გ', 'en' => '199 g']],
                    [['ka' => 'დამუხტვა', 'en' => 'Charging'], ['ka' => 'USB‑C, MagSafe 25W', 'en' => 'USB‑C, MagSafe 25W']],
                    [['ka' => '5G', 'en' => '5G'], ['ka' => 'დიახ', 'en' => 'Yes']],
                ],
            ],
            ['id' => 's25', 'cat' => 'mobile', 'sub' => 'samsung-galaxy', 'brand' => 'Samsung', 'name' => 'Galaxy S25 Ultra, 512 GB, Titanium Black', 'price' => 3999, 'new' => true],
            ['id' => 'px', 'cat' => 'mobile', 'sub' => 'google-pixel', 'brand' => 'Google', 'name' => 'Pixel 10 Pro, 256 GB, Obsidian', 'price' => 3299, 'new' => true],
            ['id' => 'x15', 'cat' => 'mobile', 'sub' => 'xiaomi', 'brand' => 'Xiaomi', 'name' => 'Xiaomi 15T Pro, 512 GB, Black', 'price' => 2199, 'old' => 2499],
            ['id' => 'mba', 'cat' => 'computers', 'sub' => 'laptops', 'brand' => 'Apple', 'name' => 'MacBook Air 13″ M4, 16 GB / 512 GB', 'price' => 3649, 'new' => true],
            ['id' => 'zb', 'cat' => 'computers', 'sub' => 'laptops', 'brand' => 'Lenovo', 'name' => 'Yoga Slim 7i, Core Ultra 7, 32 GB / 1 TB', 'price' => 3199],
            ['id' => 'ipad', 'cat' => 'computers', 'sub' => 'tablets', 'brand' => 'Apple', 'name' => 'iPad Air 11″ M3, Wi‑Fi, 128 GB', 'price' => 1899],
            ['id' => 'mon', 'cat' => 'computers', 'sub' => 'monitors', 'brand' => 'Dell', 'name' => ['ka' => 'UltraSharp 27″ 4K USB‑C მონიტორი', 'en' => 'UltraSharp 27″ 4K USB‑C Monitor'], 'price' => 1549, 'old' => 1799],
            ['id' => 'tv', 'cat' => 'tv', 'sub' => 'tvs', 'brand' => 'LG', 'name' => ['ka' => 'OLED evo C5 55″ 4K სმარტ ტელევიზორი', 'en' => 'OLED evo C5 55″ 4K Smart TV'], 'price' => 3799, 'old' => 4599],
            ['id' => 'xm6', 'cat' => 'tv', 'sub' => 'headphones', 'brand' => 'Sony', 'name' => ['ka' => 'WH-1000XM6 უსადენო ყურსასმენი ხმაურის ჩახშობით', 'en' => 'WH-1000XM6 Wireless Noise Cancelling Headphones'], 'price' => 999, 'old' => 1299],
            ['id' => 'ap', 'cat' => 'tv', 'sub' => 'headphones', 'brand' => 'Apple', 'name' => ['ka' => 'AirPods Pro 2, USB‑C დამტენი ქეისით', 'en' => 'AirPods Pro 2 with USB‑C Charging Case'], 'price' => 749, 'old' => 899],
            ['id' => 'jbl', 'cat' => 'tv', 'sub' => 'speakers', 'brand' => 'JBL', 'name' => ['ka' => 'Charge 6 პორტატული წყალგამძლე დინამიკი', 'en' => 'Charge 6 Portable Waterproof Speaker'], 'price' => 449, 'old' => 579],
            ['id' => 'ps5', 'cat' => 'gaming', 'sub' => 'consoles', 'brand' => 'Sony', 'name' => ['ka' => 'PlayStation 5 Pro კონსოლი, 2 TB', 'en' => 'PlayStation 5 Pro Console, 2 TB'], 'price' => 2299, 'old' => 2499],
            ['id' => 'ns2', 'cat' => 'gaming', 'sub' => 'consoles', 'brand' => 'Nintendo', 'name' => ['ka' => 'Nintendo Switch 2 კონსოლი', 'en' => 'Nintendo Switch 2 Console'], 'price' => 1599, 'new' => true],
            ['id' => 'ds', 'cat' => 'gaming', 'sub' => 'controllers', 'brand' => 'Sony', 'name' => ['ka' => 'DualSense Edge უსადენო ჯოისტიკი', 'en' => 'DualSense Edge Wireless Controller'], 'price' => 649],
            ['id' => 'xsx', 'cat' => 'gaming', 'sub' => 'consoles', 'brand' => 'Microsoft', 'name' => 'Xbox Series X, 1 TB', 'price' => 1899, 'old' => 2099],
            ['id' => 'a7', 'cat' => 'photo', 'sub' => 'cameras', 'brand' => 'Sony', 'name' => ['ka' => 'Alpha 7 IV უსარკო კამერა, 28–70 მმ', 'en' => 'Alpha 7 IV Mirrorless Camera, 28–70 mm'], 'price' => 6999],
            ['id' => 'gp', 'cat' => 'photo', 'sub' => 'action_cameras', 'brand' => 'GoPro', 'name' => ['ka' => 'HERO13 Black ექშენ კამერა', 'en' => 'HERO13 Black Action Camera'], 'price' => 1349, 'old' => 1549],
            ['id' => 'dji', 'cat' => 'photo', 'sub' => 'drones', 'brand' => 'DJI', 'name' => ['ka' => 'Mini 4 Pro დრონი, RC 2 პულტით', 'en' => 'Mini 4 Pro Drone with RC 2 Controller'], 'price' => 3299],
            ['id' => 'osmo', 'cat' => 'photo', 'sub' => 'tripods', 'brand' => 'DJI', 'name' => ['ka' => 'Osmo Mobile 7P სტაბილიზატორი', 'en' => 'Osmo Mobile 7P Gimbal'], 'price' => 499, 'new' => true],
            ['id' => 'rv', 'cat' => 'smart', 'sub' => 'robot_vacuums', 'brand' => 'Roborock', 'name' => ['ka' => 'S8 MaxV Ultra რობოტი მტვერსასრუტი', 'en' => 'S8 MaxV Ultra Robot Vacuum'], 'price' => 4299, 'old' => 4999],
            ['id' => 'hue', 'cat' => 'smart', 'sub' => 'smart_lighting', 'brand' => 'Philips', 'name' => ['ka' => 'Hue White & Color სმარტ ნათურა, 3 ც', 'en' => 'Hue White & Color Smart Bulb, 3 pcs'], 'price' => 399],
            ['id' => 'cam', 'cat' => 'smart', 'sub' => 'security_cameras', 'brand' => 'TP-Link', 'name' => ['ka' => 'Tapo C520WS გარე სათვალთვალო კამერა', 'en' => 'Tapo C520WS Outdoor Security Camera'], 'price' => 269],
            ['id' => 'echo', 'cat' => 'smart', 'sub' => 'voice_assistants', 'brand' => 'Amazon', 'name' => ['ka' => 'Echo Dot 5 სმარტ დინამიკი', 'en' => 'Echo Dot 5 Smart Speaker'], 'price' => 219, 'old' => 259],
            ['id' => 'ip16e', 'cat' => 'mobile', 'sub' => 'apple-iphone', 'brand' => 'Apple', 'name' => 'iPhone 16e, 128 GB, White', 'price' => 2199],
            ['id' => 'a56', 'cat' => 'mobile', 'sub' => 'samsung-galaxy', 'brand' => 'Samsung', 'name' => 'Galaxy A56 5G, 256 GB, Awesome Graphite', 'price' => 1299, 'old' => 1449],
            ['id' => 'rn14', 'cat' => 'mobile', 'sub' => 'xiaomi', 'brand' => 'Xiaomi', 'name' => 'Redmi Note 14 Pro+, 512 GB', 'price' => 1399],
            ['id' => 'mbp', 'cat' => 'computers', 'sub' => 'laptops', 'brand' => 'Apple', 'name' => 'MacBook Pro 14″ M4 Pro, 24 GB / 1 TB', 'price' => 6999, 'new' => true],
            ['id' => 'rog', 'cat' => 'computers', 'sub' => 'laptops', 'brand' => 'ASUS', 'name' => 'ROG Zephyrus G16, RTX 4070, 32 GB', 'price' => 5899, 'old' => 6499],
            ['id' => 'mx', 'cat' => 'computers', 'sub' => 'peripherals', 'brand' => 'Logitech', 'name' => ['ka' => 'MX Master 3S უსადენო მაუსი', 'en' => 'MX Master 3S Wireless Mouse'], 'price' => 329],
            ['id' => 'q8', 'cat' => 'tv', 'sub' => 'tvs', 'brand' => 'Samsung', 'name' => ['ka' => 'QLED Q80D 65″ 4K სმარტ ტელევიზორი', 'en' => 'QLED Q80D 65″ 4K Smart TV'], 'price' => 3199],
            ['id' => 'sb', 'cat' => 'tv', 'sub' => 'soundbars', 'brand' => 'Sony', 'name' => ['ka' => 'Bravia Theatre Bar 8 საუნდბარი', 'en' => 'Bravia Theatre Bar 8 Soundbar'], 'price' => 2099, 'old' => 2399],
            ['id' => 'bose', 'cat' => 'tv', 'sub' => 'headphones', 'brand' => 'Bose', 'name' => ['ka' => 'QuietComfort Ultra უსადენო ყურსასმენი', 'en' => 'QuietComfort Ultra Wireless Headphones'], 'price' => 1199],
            ['id' => 'ps5s', 'cat' => 'gaming', 'sub' => 'consoles', 'brand' => 'Sony', 'name' => 'PlayStation 5 Slim Digital, 1 TB', 'price' => 1499],
            ['id' => 'g733', 'cat' => 'gaming', 'sub' => 'gaming_accessories', 'brand' => 'Logitech', 'name' => ['ka' => 'G733 Lightspeed გეიმინგ ყურსასმენი', 'en' => 'G733 Lightspeed Gaming Headset'], 'price' => 449, 'old' => 529],
            ['id' => 'q3', 'cat' => 'gaming', 'sub' => 'vr', 'brand' => 'Meta', 'name' => ['ka' => 'Quest 3 VR სათვალე, 512 GB', 'en' => 'Quest 3 VR Headset, 512 GB'], 'price' => 1999],
            ['id' => 'z50', 'cat' => 'photo', 'sub' => 'cameras', 'brand' => 'Nikon', 'name' => ['ka' => 'Z50 II უსარკო კამერა, 16–50 მმ', 'en' => 'Z50 II Mirrorless Camera, 16–50 mm'], 'price' => 3199, 'new' => true],
            ['id' => 'ins', 'cat' => 'photo', 'sub' => 'action_cameras', 'brand' => 'Insta360', 'name' => ['ka' => 'X4 360° ექშენ კამერა', 'en' => 'X4 360° Action Camera'], 'price' => 1699],
            ['id' => 'neo', 'cat' => 'photo', 'sub' => 'drones', 'brand' => 'DJI', 'name' => ['ka' => 'Neo მინი დრონი', 'en' => 'Neo Mini Drone'], 'price' => 649, 'old' => 749],
            ['id' => 'rv2', 'cat' => 'smart', 'sub' => 'robot_vacuums', 'brand' => 'Xiaomi', 'name' => ['ka' => 'Robot Vacuum X20+ რობოტი მტვერსასრუტი', 'en' => 'Robot Vacuum X20+'], 'price' => 1899],
            ['id' => 'nest', 'cat' => 'smart', 'sub' => 'smart_lighting', 'brand' => 'Govee', 'name' => ['ka' => 'RGBIC LED ლენტი, 10 მ', 'en' => 'RGBIC LED Strip, 10 m'], 'price' => 189],
            ['id' => 'ring', 'cat' => 'smart', 'sub' => 'security_cameras', 'brand' => 'Ring', 'name' => ['ka' => 'Video Doorbell Pro 2 ვიდეო ზარი', 'en' => 'Video Doorbell Pro 2'], 'price' => 899, 'old' => 999],
            ['id' => 'gtab-s10fe', 'cat' => 'computers', 'sub' => 'tablets', 'brand' => 'Samsung', 'name' => 'Galaxy Tab S10 FE, 128 GB', 'price' => 1199, 'old' => 1549],
        ];
    }
}
