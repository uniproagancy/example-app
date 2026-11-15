<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Services\Import\SpecNames;
use App\Support\Catalog;
use App\Support\CategoryTree;
use App\Support\Slug;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('categories:translate {--dry-run : List the translations without saving them}')]
#[Description('Give categories that still have a Georgian or empty English name their English name and slug')]
class TranslateCategoryNames extends Command
{
    public const NAMES = [
        'მსხვილი საყოფაცხოვრებო ტექნიკა' => 'Large home appliances',
        'მაცივარი' => 'Refrigerators',
        'სარეცხი მანქანა' => 'Washing machines',
        'საშრობი მანქანა' => 'Dryers',
        'ჭურჭლის სარეცხი მანქანა' => 'Dishwashers',
        'გაზქურა' => 'Gas stoves',
        'გამწოვი' => 'Cooker hoods',
        'ჩასაშენებელი ტექნიკა' => 'Built-in appliances',
        'კლიმატური ტექნიკა' => 'Climate control',
        'წყლის დისპენსერი' => 'Water dispensers',
        'საყინულე მაცივარი' => 'Freezers',
        'ჩასაშენებელი ელექტრო ღუმელი' => 'Built-in electric ovens',
        'ჩასაშენებელი ქურა' => 'Built-in hobs',
        'ჩასაშენებელი გამწოვი' => 'Built-in cooker hoods',
        'ჩასაშენებელი მიკროტალღური' => 'Built-in microwaves',
        'კონდიციონერი' => 'Air conditioners',
        'ჰაერის გამწმენდი & დამატენიანებელი' => 'Air purifiers & humidifiers',
        'ელექტრო გამათბობელი' => 'Electric heaters',
        'ვენტილატორები' => 'Fans',
        'გაზის გამათბობელი' => 'Gas heaters',
        'დენის წყლის გამაცხელებელი' => 'Electric water heaters',
        'ცენტრალური გათბობა' => 'Central heating',
        'გაზის წყლის გამაცხელებელი' => 'Gas water heaters',
        'გათბობის აქსესუარები' => 'Heating accessories',
        'წვრილი საოჯახო ტექნიკა' => 'Small home appliances',
        'ყავის აპარატები და აქსესუარები' => 'Coffee machines and accessories',
        'სამზარეულოს წვრილი ტექნიკა' => 'Small kitchen appliances',
        'მიკროტალღური ღუმელი' => 'Microwave ovens',
        'ელექტრო ჩაიდანი' => 'Electric kettles',
        'სამზარეულოს კომბაინი' => 'Food processors',
        'ხორცსაკეპი' => 'Meat grinders',
        'უთო' => 'Irons',
        'ყავის აპარატი' => 'Coffee machines',
        'ყავის აპარატის აქსესუარები' => 'Coffee machine accessories',
        'ყავის საფქვავი' => 'Coffee grinders',
        'ყავის მადუღარა' => 'Coffee makers',
        'ყავა' => 'Coffee',
        'რძის ამომყვანი' => 'Milk frothers',
        'ელექტრო ღუმელი' => 'Electric ovens',
        'ბლენდერი და ჩოპერი' => 'Blenders and choppers',
        'პურის საცხობი' => 'Bread makers',
        'მიქსერი' => 'Mixers',
        'მულტი სახარში' => 'Multicookers',
        'ტოსტერი' => 'Toasters',
        'გრილი' => 'Grills',
        'წვენსაწური' => 'Juicers',
        'ჩირის აპარატი' => 'Food dehydrators',
        'ყინულის აპარატი' => 'Ice makers',
        'ღვინის გასახსნელი' => 'Wine openers',
        'სამზარეულოს სასწორი' => 'Kitchen scales',
        'სამზარეულოს ტექნიკის აქსესუარები' => 'Kitchen appliance accessories',
        'სლაისერი' => 'Slicers',
        'სენდვიჩ მეიქერი' => 'Sandwich makers',
        'ვაფლის მეიქერი' => 'Waffle makers',
        'სლაშ მეიქერი' => 'Slush makers',
        'აეროგრილები' => 'Air fryers',
        'ტელევიზორები და აუდიო სისტემები' => 'TVs and audio systems',
        'ტელევიზორი' => 'TVs',
        'ტელევიზორის აქსესუარები' => 'TV accessories',
        'პროექტორი' => 'Projectors',
        'პროექტორის აქსესუარები' => 'Projector accessories',
        'აუდიო სისტემები' => 'Audio systems',
        'სახლის კინოთეატრი' => 'Home theaters',
        'კედლის საკიდი' => 'Wall mounts',
        'კაბელები და ადაპტერები' => 'Cables and adapters',
        'საუნდბარი' => 'Soundbars',
        'Party დინამიკი' => 'Party speakers',
        'ფირსაკრავი' => 'Turntables',
        'PC დინამიკები' => 'PC speakers',
        'მობილური ტელეფონები და აქსესუარები' => 'Mobile phones and accessories',
        'მობილური ტელეფონები' => 'Mobile phones',
        'სმარტ საათები' => 'Smartwatches',
        'სმარტ საათის აქსესუარები' => 'Smartwatch accessories',
        'მობილური ტელეფონის აქსესუარები' => 'Mobile phone accessories',
        'სმარტ სათვალეები' => 'Smart glasses',
        'დამცავი ქეისი' => 'Protective cases',
        'Micro SD ბარათი' => 'Micro SD cards',
        'დამტენი ადაპტერი' => 'Charging adapters',
        'ეკრანის დამცავი' => 'Screen protectors',
        'პორტატული დინამიკი' => 'Portable speakers',
        'პორტატული დამტენი/ფაუერ ბანკი' => 'Power banks',
        'უსადენო დამტენი' => 'Wireless chargers',
        'დენის ადაპტერი' => 'Power adapters',
        'USB კაბელი' => 'USB cables',
        'მანქანის სამაგრი' => 'Car holders',
        'მანქანის დამტენი' => 'Car chargers',
        'სმარტფონის სათამაშო კონტროლერი' => 'Smartphone game controllers',
        'ვიდეო სტაბილიზატორი/გიმბალი' => 'Gimbals and stabilizers',
        'სელფის ჯოხი' => 'Selfie sticks',
        'კომპიუტერული ტექნიკა და აქსესუარები' => 'Computers and accessories',
        'ნოუთბუქი' => 'Laptops',
        'ნოუთბუქის აქსესუარები' => 'Laptop accessories',
        'პლანშეტები' => 'Tablets',
        'პლანშეტის აქსესუარები' => 'Tablet accessories',
        'პანელური / All-in-One კომპიუტერები' => 'All-in-One computers',
        'სისტემური ბლოკი' => 'Desktop computers',
        'მონიტორი' => 'Monitors',
        'მონიტორის აქსესუარები' => 'Monitor accessories',
        'PC კომპონენტები' => 'PC components',
        'ბეჭდვა და კოპირება' => 'Printing and copying',
        'ქსელური მოწყობილობები' => 'Networking',
        'კომპიუტერის აქსესუარები' => 'Computer accessories',
        'კვების წყაროები' => 'Power supplies',
        'ინტერაქტიული ეკრანი' => 'Interactive displays',
        'ნოუთბუქის ჩანთა' => 'Laptop bags',
        'ნოუთბუქის დამტენი' => 'Laptop chargers',
        'ნოუთბუქის ქულერი' => 'Laptop coolers',
        'ნოუთბუქის სადგამი' => 'Laptop stands',
        'პლანშეტის ქეისი' => 'Tablet cases',
        'პლანშეტის ეკრანის დამცავი' => 'Tablet screen protectors',
        'პლანშეტის სტილუსი' => 'Tablet styluses',
        'პლანშეტის კლავიატურა' => 'Tablet keyboards',
        'პროცესორი' => 'Processors',
        'ქულერი' => 'CPU coolers',
        'კვების ბლოკი' => 'Power supply units',
        'ვიდეო ბარათი' => 'Graphics cards',
        'თერმო პასტა' => 'Thermal paste',
        'დედა დაფა' => 'Motherboards',
        'მეხსიერება SSD/HDD' => 'Storage SSD/HDD',
        'ქეისი' => 'PC cases',
        'ოპერატიული მეხსიერება/RAM' => 'Memory/RAM',
        'პრინტერი' => 'Printers',
        'სკანერი' => 'Scanners',
        'კარტრიჯები და ტონერები' => 'Cartridges and toners',
        'როუტერი' => 'Routers',
        'ქსელის კაბელები' => 'Network cables',
        'ქსელის გამანაწილებელი/სვიჩი' => 'Network switches',
        'მაუსი' => 'Mice',
        'მაუსპადი' => 'Mouse pads',
        'კლავიატურა' => 'Keyboards',
        'კომპიუტერის დინამიკი' => 'Computer speakers',
        'კომპიუტერის ვებკამერა' => 'Webcams',
        'სტრიმინგის აქსესუარები' => 'Streaming accessories',
        'გარე მყარი დისკები' => 'External hard drives',
        'USB ფლეშ მეხსიერება' => 'USB flash drives',
        'USB გამანაწილებლები' => 'USB hubs',
        'ეკრანის საწმენდი საშუალებები' => 'Screen cleaning supplies',
        'დენის ფილტრი და ადაპტერი' => 'Surge protectors and adapters',
        'უწყვეტი კვების წყარო/UPS' => 'Uninterruptible power supplies/UPS',
        'უწყვეტი კვების წყაროს აქსესუარები' => 'UPS accessories',
        'კომპიუტერის ქეისები' => 'Computer cases',
        'ფოტო და ვიდეო' => 'Photo and video',
        'ციფრული კამერა' => 'Digital cameras',
        'ციფრული კამერის აქსესუარები' => 'Digital camera accessories',
        'ვიდეო კამერა' => 'Video cameras',
        'ექშენ კამერა' => 'Action cameras',
        'დრონი' => 'Drones',
        'ტელესკოპი' => 'Telescopes',
        'ბინოკლი' => 'Binoculars',
        'მიკროსკოპი' => 'Microscopes',
        'კამერის ობიექტივი' => 'Camera lenses',
        'კამერის ჩანთა' => 'Camera bags',
        'კამერის მეხსიერების ბარათი' => 'Camera memory cards',
        'კამერის აკუმულატორი' => 'Camera batteries',
        'დრონის აქსესუარები' => 'Drone accessories',
        'გეიმინგი' => 'Gaming',
        'სათამაშო კონსოლები' => 'Game consoles',
        'გეიმინგ მონიტორი' => 'Gaming monitors',
        'გეიმინგ ლეპტოპი' => 'Gaming laptops',
        'PC გეიმინგი' => 'PC gaming',
        'გეიმინგ აქსესუარები' => 'Gaming accessories',
        'თამაშები' => 'Games',
        'Gaming მაგიდები' => 'Gaming desks',
        'PlayStation-ის აქსესუარები' => 'PlayStation accessories',
        'PlayStation-ის თამაშები' => 'PlayStation games',
        'Xbox-ის აქსესუარები' => 'Xbox accessories',
        'Xbox-ის თამაშები' => 'Xbox games',
        'პორტატული კონსოლები' => 'Handheld consoles',
        'Meta VR სათვალე' => 'Meta VR headsets',
        'გეიმინგ სისტემური ბლოკი' => 'Gaming desktops',
        'PC გეიმინგ კომპონენტები' => 'PC gaming components',
        'გეიმინგ სავარძელი' => 'Gaming chairs',
        'გეიმინგ მაგიდა' => 'Gaming desks',
        'გეიმინგ საჭე' => 'Racing wheels',
        'გეიმინგ მაუსი' => 'Gaming mice',
        'გეიმინგ ზურგჩანთა' => 'Gaming backpacks',
        'გეიმინგ მიკროფონი' => 'Gaming microphones',
        'გეიმინგ კლავიატურა' => 'Gaming keyboards',
        'გეიმინგ მაუსპადი' => 'Gaming mouse pads',
        'გეიმინგ ყურსასმენები' => 'Gaming headsets',
        'კონტროლერები' => 'Controllers',
        'თავის მოვლა' => 'Personal care',
        'თმის უთო' => 'Hair straighteners',
        'თმის ფენი' => 'Hair dryers',
        'თმის სახვევი' => 'Hair curlers',
        'ეპილატორი' => 'Epilators',
        'ელექტრო ჯაგრისები & ირიგატორები' => 'Electric toothbrushes & water flossers',
        'ტრიმერი' => 'Trimmers',
        'სასწორი' => 'Scales',
        'მასაჟორი' => 'Massagers',
        'წვერსაპარსი' => 'Shavers',
        'კბილის ჯაგრისები' => 'Toothbrushes',
        'სახლის და ეზოს მოვლა' => 'Home and garden care',
        'მოპი და იატაკის საწმენდი' => 'Mops and floor cleaners',
        'მტვერსასრუტი' => 'Vacuum cleaners',
        'მტვერსასრუტის აქსესუარები' => 'Vacuum cleaner accessories',
        'რობოტი მტვერსასრუტი' => 'Robot vacuums',
        'ორთქლის უთო' => 'Steam irons',
        'ელექტრო ტრანსპორტი' => 'Electric transport',
        'ელექტრო სკუტერი' => 'Electric scooters',
        'ელექტრო ველოსიპედი' => 'Electric bikes',
        'ჰოვერბორდები' => 'Hoverboards',
        'მანქანის ხელსაწყოები და აქსესუარები' => 'Car tools and accessories',
        'ვიდეო რეგისტრატორები' => 'Dash cams',
        'მანქანის კომპრესორები' => 'Car air compressors',
        'ხელსაწყოები' => 'Tools',
        'ჭკვიანი სახლი' => 'Smart home',
        'ჭკვიანი ჩამრთველები' => 'Smart switches',
        'ჭკვიანი განათება' => 'Smart lighting',
        'ჭკვიანი სენსორები' => 'Smart sensors',
        'ჭკვიანი საკეტები' => 'Smart locks',
        'ჭკვიანი კამერა' => 'Smart cameras',
        'კონტროლი და ავტომატიზაცია' => 'Control and automation',
        'ყურსასმენები' => 'Headphones',
        'უსადენო ყურსასმენები' => 'Wireless headphones',
        'საბავშვო ყურსასმენი' => 'Kids headphones',
        'კომპიუტერის ყურსასმენი' => 'Computer headsets',
        'ყურსასმენის აქსესუარები' => 'Headphone accessories',
        'სადენიანი ყურსასმენები' => 'Wired headphones',
        'მშობელი და ბავშვი' => 'Parent and baby',
        'საბავშვო ავეჯი' => 'Kids furniture',
        'ავეჯი' => 'Furniture',
        'თარო' => 'Shelves',
        'ტორშერი' => 'Floor lamps',
        'სკამი' => 'Chairs',
        'მაგიდის სანათი' => 'Desk lamps',
        'კარადა' => 'Wardrobes',
        'საკიდი' => 'Hangers',
        'ორადგილიანი საწოლი' => 'Double beds',
        'ტელევიზორის მაგიდა' => 'TV stands',
        'ლეპტოპის მაგიდა' => 'Laptop desks',
        'მატრასები' => 'Mattresses',
        'ყავის მაგიდა' => 'Coffee tables',
        'მაგიდის და სკამის კომპლექტი' => 'Table and chair sets',
        'საოფისე/სამეცადინო მაგიდები' => 'Office and study desks',
        'სკამები და სავარძლები' => 'Chairs and armchairs',
        'მაგიდა' => 'Tables',
        'ორსართულიანი საწოლი' => 'Bunk beds',
        'ორგანაიზერი' => 'Organizers',
        'საოფისე სავარძელი' => 'Office chairs',
        'სარკეები' => 'Mirrors',
        'ტუმბო' => 'Nightstands',
        'ფეხსაცმლის კარადა' => 'Shoe cabinets',
        'საოფისე ავეჯი' => 'Office furniture',
        'საათები' => 'Clocks',
        'მონიტორინგი და უსაფრთხოება' => 'Monitoring and security',
        'უსაფრთხოების კამერები' => 'Security cameras',
        'GPS ტრეკერები' => 'GPS trackers',
        'რაციები' => 'Walkie-talkies',
        'სამზარეულო აქსესუარები' => 'Kitchen accessories',
        'ქვაბი' => 'Pots',
        'BBQ გრილები' => 'BBQ grills',
        'ტაფა' => 'Frying pans',
        'დანა-ჩანგლის ნაკრები' => 'Cutlery sets',
        'სამზარეულოს დანები' => 'Kitchen knives',
        'ბოთლები' => 'Bottles',
        'სამზარეულოს ჭურჭელი' => 'Cookware',
        'ეზოს ფურნიტურა' => 'Garden furniture',
        'ჩაიდნები' => 'Kettles',
        'სათამაშოები' => 'Toys',
        'საბავშვო მანქანები' => 'Kids cars',
        'თოჯინები' => 'Dolls',
        'კარტინგები' => 'Go-karts',
        'ველოსიპედი' => 'Bicycles',
        'მოტოციკლები' => 'Motorcycles',
        'ახალი წელი' => 'New Year',
        'სხვა და სხვა' => 'Miscellaneous',
    ];

    public function handle(): int
    {
        $translated = [];
        $missing = [];

        Category::query()->with('translations')->orderBy('id')->each(function (Category $category) use (&$translated, &$missing): void {
            $georgian = trim((string) $category->translate('ka', false)?->title);
            $currentEnglish = trim((string) $category->translate('en', false)?->title);

            if ($georgian === '' || ($currentEnglish !== '' && $currentEnglish !== $georgian && ! SpecNames::isGeorgian($currentEnglish))) {
                return;
            }

            $english = static::english($georgian);

            if ($english === null) {
                $missing[] = [$category->id, $georgian];

                return;
            }

            if ($english === $currentEnglish) {
                return;
            }

            $translated[] = [$category->id, $georgian, $english];

            if (! $this->option('dry-run')) {
                $category->saveTranslations(['en' => ['title' => $english, 'slug' => Slug::make($english)]]);
            }
        });

        if ($translated !== []) {
            $this->table(['id', 'ka', 'en'], $translated);
        }

        if ($missing !== []) {
            $this->warn('No English name known for:');
            $this->table(['id', 'ka'], $missing);
        }

        if ($translated !== [] && ! $this->option('dry-run')) {
            CategoryTree::flush();
            Catalog::flush();
        }

        $this->info(($this->option('dry-run') ? 'Would translate ' : 'Translated ').count($translated).' categor'.(count($translated) === 1 ? 'y' : 'ies').'.');

        return self::SUCCESS;
    }

    public static function english(string $georgian): ?string
    {
        $georgian = trim($georgian);

        if (isset(self::NAMES[$georgian])) {
            return self::NAMES[$georgian];
        }

        return SpecNames::isGeorgian($georgian) ? null : $georgian;
    }
}
