<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            'delivery' => [
                'ka' => ['title' => 'მიწოდების პირობები', 'intro' => 'შეკვეთას კურიერი მოგიტანს მთელი საქართველოს მასშტაბით.', 'body' => '<h3>ვადები</h3><p>თბილისში შეკვეთა მიწოდდება 2 სამუშაო დღეში, რეგიონებში — 3–7 სამუშაო დღეში.</p><h3>ღირებულება</h3><p>მიწოდების ღირებულება გამოჩნდება შეკვეთის გაფორმებისას.</p><h3>მიღება</h3><ul><li>შეამოწმე ნივთი კურიერის თანდასწრებით.</li><li>დაზიანების შემთხვევაში არ ჩაიბარო და დაგვიკავშირდი.</li></ul>'],
                'en' => ['title' => 'Delivery', 'intro' => 'Our courier delivers your order anywhere in Georgia.', 'body' => '<h3>Timing</h3><p>Orders in Tbilisi arrive in 2 working days, in the regions in 3–7 working days.</p><h3>Cost</h3><p>The delivery cost is shown when you place the order.</p><h3>Receiving</h3><ul><li>Check the item in front of the courier.</li><li>If it is damaged, do not accept it and contact us.</li></ul>'],
            ],
            'warranty' => [
                'ka' => ['title' => 'გარანტია', 'intro' => 'ყველა ნივთს აქვს მწარმოებლის ან მაღაზიის ოფიციალური გარანტია.', 'body' => '<p>გარანტიის ვადა მითითებულია პროდუქტის გვერდზე და ჩეკზე.</p><h3>რას არ მოიცავს</h3><ul><li>მექანიკურ დაზიანებას და სითხის მოხვედრას.</li><li>არაავტორიზებულ შეკეთებას.</li></ul><h3>როგორ მივმართო</h3><p>მოიტანე ნივთი ჩეკით ან დაგვიკავშირდი — მივუთითებთ უახლოეს სერვის ცენტრს.</p>'],
                'en' => ['title' => 'Warranty', 'intro' => 'Every item comes with an official manufacturer or store warranty.', 'body' => '<p>The warranty period is shown on the product page and on the receipt.</p><h3>Not covered</h3><ul><li>Mechanical damage and liquid damage.</li><li>Unauthorised repairs.</li></ul><h3>How to claim</h3><p>Bring the item with the receipt or contact us and we will point you to the nearest service centre.</p>'],
            ],
            'installments' => [
                'ka' => ['title' => 'განვადება', 'intro' => 'შეიძინე ახლა და გადაიხადე ნაწილ-ნაწილ — ონლაინ განაცხადით, ფილიალში მისვლის გარეშე.', 'body' => '<p>განვადება ხელმისაწვდომია საქართველოს ბანკის, TBC-ისა და Credo-ს მეშვეობით, 3-დან 24 თვემდე.</p><h3>როგორ</h3><ul><li>შეკვეთისას აირჩიე განვადება.</li><li>შეავსე ბანკის ონლაინ განაცხადი.</li><li>დამტკიცების შემდეგ შეკვეთას დაგიდასტურებთ.</li></ul>'],
                'en' => ['title' => 'Instalments', 'intro' => 'Buy now and pay in parts — apply online, no branch visit needed.', 'body' => '<p>Instalments are available through Bank of Georgia, TBC and Credo, from 3 to 24 months.</p><h3>How</h3><ul><li>Choose instalments at checkout.</li><li>Fill in the bank\'s online application.</li><li>Once approved, we confirm your order.</li></ul>'],
            ],
            'terms' => [
                'ka' => ['title' => 'წესები და პირობები', 'intro' => 'საიტით სარგებლობით ეთანხმები ქვემოთ მოცემულ პირობებს.', 'body' => '<h3>ზოგადი</h3><p>ფასები მითითებულია ლარში და მოიცავს დღგ-ს. ფასი და მარაგი შეიძლება შეიცვალოს გაფრთხილების გარეშე.</p><h3>შეკვეთა</h3><p>შეკვეთა ძალაშია მას შემდეგ, რაც ოპერატორი დაგიდასტურებს ან გადახდა დასრულდება.</p>'],
                'en' => ['title' => 'Terms and conditions', 'intro' => 'By using the site you agree to the terms below.', 'body' => '<h3>General</h3><p>Prices are in lari and include VAT. Prices and stock may change without notice.</p><h3>Orders</h3><p>An order is binding once an operator confirms it or the payment is completed.</p>'],
            ],
            'privacy' => [
                'ka' => ['title' => 'კონფიდენციალურობა', 'intro' => 'შენი მონაცემები გამოიყენება მხოლოდ შეკვეთის შესასრულებლად.', 'body' => '<h3>რას ვაგროვებთ</h3><p>სახელს, ტელეფონს, ელ-ფოსტას და მიწოდების მისამართს.</p><h3>რისთვის</h3><p>შეკვეთის დასადასტურებლად, მიწოდებისთვის და გადახდისთვის. მესამე მხარეს მონაცემებს გადავცემთ მხოლოდ მიწოდებისა და გადახდისთვის აუცილებელ მოცულობით.</p>'],
                'en' => ['title' => 'Privacy', 'intro' => 'Your data is used only to fulfil your order.', 'body' => '<h3>What we collect</h3><p>Your name, phone, email and delivery address.</p><h3>Why</h3><p>To confirm, deliver and take payment for your order. We share data with third parties only as far as delivery and payment require.</p>'],
            ],
        ];

        $position = 0;

        foreach ($pages as $slug => $translations) {
            $page = Page::query()->firstOrCreate(['slug' => $slug], [
                'is_active' => true,
                'in_footer' => true,
                'position' => ++$position,
            ]);

            if ($page->wasRecentlyCreated) {
                $page->saveTranslations($translations);
            }
        }
    }
}
