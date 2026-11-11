<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Support\CategoryTree;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    protected array $usedSlugs = [];

    public function run(): void
    {
        Category::query()->whereNull('parent_id')->get()->each->delete();

        $queue = collect($this->tree())->map(fn (array $node, int $index) => [$node, null, $index + 1, null])->all();

        while ($queue !== []) {
            [$node, $parent, $position, $parentSlugs] = array_shift($queue);
            [$category, $slugs] = $this->create($node, $parent, $position, $parentSlugs);

            foreach ($node['children'] ?? [] as $childIndex => $child) {
                $queue[] = [$child, $category, $childIndex + 1, $slugs];
            }
        }

        CategoryTree::flush();
    }

    protected function create(array $node, ?Category $parent, int $position, ?array $parentSlugs): array
    {
        $category = Category::query()->create([
            'parent_id' => $parent?->id,
            'code' => $node['code'],
            'icon' => $node['icon'] ?? null,
            'position' => $position,
            'show_on_main' => $parent === null,
        ]);

        $englishTitle = ((array) $node['en'])[0];
        $slug = $this->uniqueSlug('en', $englishTitle, $parentSlugs['en'] ?? null);
        $slugs = ['ka' => $slug, 'en' => $slug];

        foreach (['ka', 'en'] as $locale) {
            [$title, $short] = array_pad((array) $node[$locale], 2, null);

            $category->translations()->create([
                'locale' => $locale,
                'title' => $title,
                'short_title' => $short,
                'slug' => $slug,
            ]);
        }

        return [$category, $slugs];
    }

    protected function uniqueSlug(string $locale, string $title, ?string $parentSlug): string
    {
        $slug = Str::slug($title, '-', $locale) ?: Str::random(6);

        if (isset($this->usedSlugs[$locale][$slug]) && $parentSlug) {
            $slug = $parentSlug.'-'.$slug;
        }

        $base = $slug;
        $suffix = 2;

        while (isset($this->usedSlugs[$locale][$slug])) {
            $slug = $base.'-'.$suffix++;
        }

        $this->usedSlugs[$locale][$slug] = true;

        return $slug;
    }

    protected function leaf(string $code, string $ka, ?string $en = null, array $children = []): array
    {
        return ['code' => $code, 'ka' => $ka, 'en' => $en ?? $ka, 'children' => $children];
    }

    protected function tree(): array
    {
        return [
            [
                'code' => 'mobile', 'icon' => 'mobile',
                'ka' => ['მობილური ტელეფონები და აქსესუარები', 'სმარტფონები'],
                'en' => ['Mobile phones and accessories', 'Smartphones'],
                'children' => [
                    $this->leaf('smartphones', 'სმარტფონები', 'Smartphones', [
                        $this->leaf('apple-iphone', 'Apple iPhone'),
                        $this->leaf('samsung-galaxy', 'Samsung Galaxy'),
                        $this->leaf('xiaomi', 'Xiaomi'),
                        $this->leaf('google-pixel', 'Google Pixel'),
                    ]),
                    $this->leaf('feature_phones', 'ღილაკიანი ტელეფონები', 'Feature phones'),
                    $this->leaf('cases', 'ქეისები და დამცავი მინები', 'Cases and screen protectors'),
                    $this->leaf('chargers', 'დამტენები და კაბელები', 'Chargers and cables'),
                    $this->leaf('power_banks', 'პორტატული დამტენები', 'Power banks'),
                    $this->leaf('smartwatches', 'სმარტ საათები და ფიტნეს სამაჯურები', 'Smartwatches and fitness bands', [
                        $this->leaf('apple-watch', 'Apple Watch'),
                        $this->leaf('galaxy-watch', 'Galaxy Watch'),
                        $this->leaf('fitness_bands', 'ფიტნეს სამაჯურები', 'Fitness bands'),
                    ]),
                    $this->leaf('phone_holders', 'ტელეფონის სამაგრები', 'Phone holders'),
                ],
            ],
            [
                'code' => 'computers', 'icon' => 'computers',
                'ka' => ['კომპიუტერული ტექნიკა და აქსესუარები', 'ლეპტოპები'],
                'en' => ['Computers and accessories', 'Laptops'],
                'children' => [
                    $this->leaf('laptops', 'ლეპტოპები', 'Laptops', [
                        $this->leaf('gaming_laptops', 'გეიმინგ ლეპტოპები', 'Gaming laptops'),
                        $this->leaf('ultrabooks', 'ულტრაბუქები', 'Ultrabooks'),
                        $this->leaf('macbook', 'MacBook'),
                        $this->leaf('business_laptops', 'ბიზნეს ლეპტოპები', 'Business laptops'),
                    ]),
                    $this->leaf('desktops', 'პერსონალური კომპიუტერები', 'Desktop computers'),
                    $this->leaf('monitors', 'მონიტორები', 'Monitors'),
                    $this->leaf('tablets', 'პლანშეტები', 'Tablets', [
                        $this->leaf('ipad', 'iPad'),
                        $this->leaf('android_tablets', 'Android პლანშეტები', 'Android tablets'),
                    ]),
                    $this->leaf('peripherals', 'პერიფერია', 'Peripherals', [
                        $this->leaf('mice', 'მაუსები', 'Mice'),
                        $this->leaf('keyboards', 'კლავიატურები', 'Keyboards'),
                        $this->leaf('webcams', 'ვებკამერები', 'Webcams'),
                    ]),
                    $this->leaf('networking', 'ქსელური მოწყობილობები', 'Networking'),
                    $this->leaf('storage', 'მეხსიერება და SSD', 'Storage and SSD'),
                ],
            ],
            [
                'code' => 'tv', 'icon' => 'tv',
                'ka' => ['ტელევიზორები და აუდიო სისტემები', 'TV და აუდიო'],
                'en' => ['TVs and audio systems', 'TV & audio'],
                'children' => [
                    $this->leaf('tvs', 'ტელევიზორები', 'TVs', [
                        $this->leaf('oled', 'OLED'),
                        $this->leaf('qled', 'QLED'),
                        $this->leaf('smart_tv', 'სმარტ TV', 'Smart TV'),
                    ]),
                    $this->leaf('soundbars', 'საუნდბარები', 'Soundbars'),
                    $this->leaf('headphones', 'ყურსასმენები', 'Headphones', [
                        $this->leaf('wireless_headphones', 'უსადენო', 'Wireless'),
                        $this->leaf('gaming_headphones', 'გეიმინგ', 'Gaming'),
                        $this->leaf('sport_headphones', 'სპორტული', 'Sport'),
                    ]),
                    $this->leaf('speakers', 'პორტატული დინამიკები', 'Portable speakers'),
                    $this->leaf('projectors', 'პროექტორები', 'Projectors'),
                    $this->leaf('tv_mounts', 'TV სამაგრები', 'TV mounts'),
                ],
            ],
            [
                'code' => 'gaming', 'icon' => 'gaming',
                'ka' => ['გეიმინგი'],
                'en' => ['Gaming'],
                'children' => [
                    $this->leaf('consoles', 'კონსოლები', 'Consoles', [
                        $this->leaf('playstation', 'PlayStation'),
                        $this->leaf('xbox', 'Xbox'),
                        $this->leaf('nintendo', 'Nintendo'),
                    ]),
                    $this->leaf('controllers', 'ჯოისტიკები', 'Controllers'),
                    $this->leaf('video_games', 'ვიდეო თამაშები', 'Video games'),
                    $this->leaf('gaming_chairs', 'გეიმინგ სავარძლები', 'Gaming chairs'),
                    $this->leaf('vr', 'VR სათვალეები', 'VR headsets'),
                    $this->leaf('gaming_accessories', 'გეიმინგ აქსესუარები', 'Gaming accessories'),
                ],
            ],
            [
                'code' => 'photo', 'icon' => 'photo',
                'ka' => ['ფოტო და ვიდეო'],
                'en' => ['Photo and video', 'Photo & video'],
                'children' => [
                    $this->leaf('cameras', 'ფოტოაპარატები', 'Cameras', [
                        $this->leaf('dslr', 'სარკისებრი', 'DSLR'),
                        $this->leaf('mirrorless', 'უსარკო', 'Mirrorless'),
                    ]),
                    $this->leaf('lenses', 'ობიექტივები', 'Lenses'),
                    $this->leaf('action_cameras', 'ექშენ კამერები', 'Action cameras'),
                    $this->leaf('drones', 'დრონები', 'Drones'),
                    $this->leaf('tripods', 'სამფეხები და სტაბილიზატორები', 'Tripods and gimbals'),
                ],
            ],
            [
                'code' => 'smart', 'icon' => 'smart',
                'ka' => ['სმარტ სახლი'],
                'en' => ['Smart home'],
                'children' => [
                    $this->leaf('smart_lighting', 'სმარტ განათება', 'Smart lighting'),
                    $this->leaf('security_cameras', 'უსაფრთხოების კამერები', 'Security cameras'),
                    $this->leaf('voice_assistants', 'ხმოვანი ასისტენტები', 'Voice assistants'),
                    $this->leaf('smart_plugs', 'სმარტ როზეტები', 'Smart plugs'),
                    $this->leaf('robot_vacuums', 'რობოტი მტვერსასრუტები', 'Robot vacuums'),
                ],
            ],
            [
                'code' => 'small', 'icon' => 'small',
                'ka' => ['წვრილი საყოფაცხოვრებო ტექნიკა', 'საყოფაცხოვრებო'],
                'en' => ['Small home appliances', 'Appliances'],
                'children' => [
                    $this->leaf('coffee_machines', 'ყავის აპარატები', 'Coffee machines'),
                    $this->leaf('blenders', 'ბლენდერები', 'Blenders'),
                    $this->leaf('kettles', 'ელექტრო ჩაიდნები', 'Electric kettles'),
                    $this->leaf('hair_dryers', 'თმის საშრობები', 'Hair dryers'),
                    $this->leaf('irons', 'უთოები', 'Irons'),
                    $this->leaf('scales', 'სასწორები', 'Scales'),
                ],
            ],
            [
                'code' => 'acc', 'icon' => 'acc',
                'ka' => ['აქსესუარები'],
                'en' => ['Accessories'],
                'children' => [
                    $this->leaf('bags', 'ჩანთები და ქეისები', 'Bags and cases'),
                    $this->leaf('batteries', 'ბატარეები', 'Batteries'),
                    $this->leaf('usb_flash', 'USB ფლეშ მეხსიერება', 'USB flash drives'),
                    $this->leaf('adapters', 'ადაპტერები', 'Adapters'),
                    $this->leaf('cleaning', 'გამწმენდი საშუალებები', 'Cleaning supplies'),
                ],
            ],
        ];
    }
}
