<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public const LEGAL_PAGES = ['delivery', 'warranty', 'terms', 'privacy'];

    public function run(): void
    {
        $position = 0;

        foreach (static::content() as $slug => $translations) {
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

    public static function content(): array
    {
        return [
            'delivery' => [
                'ka' => [
                    'title' => 'მიწოდების პირობები',
                    'intro' => 'შეკვეთას კურიერი მოგიტანს საქართველოს ნებისმიერ ქალაქსა და სოფელში. 100 ₾-ზე მეტ შეკვეთაზე მიწოდება უფასოა.',
                    'meta_description' => 'ELIO.GE-ს მიწოდების ვადები და ღირებულება: თბილისში 2 სამუშაო დღე, რეგიონებში 3–7 დღე, 100 ₾-დან უფასო მიწოდება.',
                    'body' => <<<'HTML'
                        <h3>ვადები და ღირებულება</h3>
                        <table>
                            <thead><tr><th>მიმართულება</th><th>ვადა</th><th>ღირებულება</th></tr></thead>
                            <tbody>
                                <tr><td>თბილისი</td><td>2 სამუშაო დღემდე</td><td>5 ₾</td></tr>
                                <tr><td>რეგიონები</td><td>3–7 სამუშაო დღე</td><td>5 ₾</td></tr>
                                <tr><td>100 ₾-ზე მეტი შეკვეთა</td><td>იგივე ვადები</td><td>უფასო</td></tr>
                            </tbody>
                        </table>
                        <p>ვადა აითვლება შეკვეთის დადასტურებიდან. ონლაინ გადახდისას შეკვეთა ავტომატურად დასტურდება, სხვა შემთხვევაში ოპერატორი დაგირეკავს სამუშაო საათებში. შაბათ-კვირა და უქმე დღეები სამუშაო დღეებად არ ითვლება.</p>
                        <p>მიწოდება არ ხორციელდება საქართველოს ოკუპირებულ ტერიტორიებზე.</p>
                        <h3>შეკვეთის სტატუსი</h3>
                        <p>შეკვეთის გაფორმებისთანავე მიიღებ შეკვეთის ნომერს. სტატუსის ცვლილებას SMS-ით გაცნობებთ, ხოლო ავტორიზებულ მომხმარებელს შეკვეთის მიმდინარეობის ნახვა პირად კაბინეტშიც შეუძლია.</p>
                        <h3>ნივთის ჩაბარება</h3>
                        <ul>
                            <li>კურიერი მიტანამდე დაგიკავშირდება მითითებულ ნომერზე. თუ ვერ დაგიკავშირდა, მიწოდება შემდეგ სამუშაო დღეზე გადაიდება.</li>
                            <li>ჩაბარებისას კურიერის თანდასწრებით შეამოწმე შეფუთვა და ნივთის გარეგნული მდგომარეობა.</li>
                            <li>თუ ნივთი ან შეფუთვა დაზიანებულია, არ ჩაიბარო და დაუყოვნებლივ დაგვიკავშირდი. დაზიანებულ ნივთს ჩვენ შევცვლით ჩვენი ხარჯით.</li>
                            <li>ნივთის ჩაბარება შეუძლია შენ მიერ მითითებულ სხვა პირსაც.</li>
                        </ul>
                        <h3>გადახდა მიწოდებისას</h3>
                        <p>თუ შეკვეთისას აირჩიე ადგილზე გადახდა, თანხას კურიერს გადაუხდი ნაღდი ფულით ან ბარათით, ნივთის ჩაბარებისას.</p>
                        <h3>შეფერხება</h3>
                        <p>თუ მიწოდება ჩვენი მიზეზით იგვიანებს, წინასწარ გაცნობებთ ახალ ვადას. თუ ახალი ვადა არ გაწყობს, შეგიძლია გააუქმო შეკვეთა და გადახდილ თანხას სრულად დაგიბრუნებთ.</p>
                        HTML,
                ],
                'en' => [
                    'title' => 'Delivery',
                    'intro' => 'Our courier delivers anywhere in Georgia. Delivery is free on orders over 100 ₾.',
                    'meta_description' => 'ELIO.GE delivery times and costs: up to 2 working days in Tbilisi, 3–7 days in the regions, free delivery over 100 ₾.',
                    'body' => <<<'HTML'
                        <h3>Times and cost</h3>
                        <table>
                            <thead><tr><th>Destination</th><th>Time</th><th>Cost</th></tr></thead>
                            <tbody>
                                <tr><td>Tbilisi</td><td>Up to 2 working days</td><td>5 ₾</td></tr>
                                <tr><td>Regions</td><td>3–7 working days</td><td>5 ₾</td></tr>
                                <tr><td>Orders over 100 ₾</td><td>Same times</td><td>Free</td></tr>
                            </tbody>
                        </table>
                        <p>Delivery time counts from order confirmation. Orders paid online are confirmed automatically; otherwise an operator calls you during working hours. Weekends and public holidays are not working days.</p>
                        <p>We do not deliver to the occupied territories of Georgia.</p>
                        <h3>Order status</h3>
                        <p>You get an order number as soon as you place the order. We text you when its status changes, and signed-in customers can follow the order in their account.</p>
                        <h3>Receiving the item</h3>
                        <ul>
                            <li>The courier calls the number you gave before arriving. If they cannot reach you, delivery moves to the next working day.</li>
                            <li>Check the packaging and the item's condition in front of the courier.</li>
                            <li>If the item or packaging is damaged, do not accept it and contact us right away. We replace damaged items at our cost.</li>
                            <li>Someone you name can receive the order for you.</li>
                        </ul>
                        <h3>Paying on delivery</h3>
                        <p>If you chose to pay on delivery, pay the courier in cash or by card when you receive the item.</p>
                        <h3>Delays</h3>
                        <p>If delivery is delayed on our side, we let you know the new date in advance. If it does not suit you, you can cancel the order and get a full refund.</p>
                        HTML,
                ],
            ],
            'warranty' => [
                'ka' => [
                    'title' => 'გარანტია და დაბრუნება',
                    'intro' => 'ნივთის დაბრუნება შეგიძლია 14 დღის განმავლობაში მიზეზის მითითების გარეშე, ხოლო ქარხნული ნაკლის შემთხვევაში გარანტია 2 წელი მოქმედებს.',
                    'meta_description' => 'ELIO.GE-ს გარანტიისა და დაბრუნების პირობები: 14 დღიანი დაბრუნება, 2 წლიანი გარანტია, შეკეთება, შეცვლა და თანხის დაბრუნება.',
                    'body' => <<<'HTML'
                        <h2>დაბრუნება 14 დღეში</h2>
                        <p>ონლაინ შეძენილ ნივთზე უარის თქმა შეგიძლია მიზეზის მითითების გარეშე, ნივთის ჩაბარებიდან 14 კალენდარული დღის განმავლობაში. თუ შეკვეთა რამდენიმე ნაწილად მოგეწოდა, ვადა ბოლო ნივთის ჩაბარებიდან აითვლება.</p>
                        <h3>როგორ დავაბრუნო</h3>
                        <ol>
                            <li>14 დღის განმავლობაში შეგვატყობინე ტელეფონით, ელ-ფოსტით ან კონტაქტის ფორმით. მიუთითე შეკვეთის ნომერი და რომელი ნივთის დაბრუნება გსურს.</li>
                            <li>შეტყობინებიდან არაუგვიანეს 7 დღისა დაგვიბრუნე ნივთი სრული კომპლექტაციით: ყუთით, აქსესუარებით, დოკუმენტებითა და საჩუქრებით, თუ ასეთი ერთვოდა.</li>
                            <li>თანხას დაგიბრუნებთ შეტყობინებიდან 14 დღის განმავლობაში, იმავე გზით, რითაც გადაიხადე. თანხას ვაბრუნებთ ნივთის მიღების ან გაგზავნის დამადასტურებელი დოკუმენტის მიღების შემდეგ.</li>
                        </ol>
                        <p>უპირობო დაბრუნებისას ნივთის უკან გამოგზავნის ხარჯს ფარავს მყიდველი. სტანდარტული მიწოდების საფასურს დაგიბრუნებთ.</p>
                        <p>თუ ნივთი ისე გამოიყენე, რომ მისი ღირებულება შემცირდა (მაგალითად, დაზიანდა ან დაკარგა კომპლექტაციის ნაწილი), ამ სხვაობის ანაზღაურება შეიძლება მოგიწიოს. ნივთის შემოწმება და გამოცდა ისე, როგორც მაღაზიაში გააკეთებდი, ღირებულებას არ ამცირებს.</p>
                        <h3>რა არ ბრუნდება უპირობოდ</h3>
                        <ul>
                            <li>ნივთი, რომლის ფასი 30 ₾-ს არ აღემატება;</li>
                            <li>შენი ინდივიდუალური შეკვეთით დამზადებული ან მორგებული ნივთი;</li>
                            <li>ჰიგიენური ნივთი, რომლის დალუქული შეფუთვაც გაიხსნა (მაგალითად, ყურსასმენები, საპარსები, კბილის ჯაგრისები);</li>
                            <li>პროგრამული უზრუნველყოფა, თამაშები და ციფრული კოდები, თუ დალუქვა დაირღვა ან კოდი გააქტიურდა;</li>
                            <li>ნივთი, რომელიც მიწოდების შემდეგ განუყოფლად შეერწყა სხვა ნივთს.</li>
                        </ul>
                        <h2>გარანტია</h2>
                        <p>ყველა ნივთს აქვს ოფიციალური გარანტია. გარანტიის ვადა მითითებულია პროდუქტის გვერდზე და ინვოისზე. მწარმოებლის გარანტიის მიუხედავად, კანონის მიხედვით ნივთზე 2 წლიანი გარანტია მოქმედებს.</p>
                        <h3>რას მოიცავს</h3>
                        <ul>
                            <li>ქარხნულ ნაკლს და დეფექტს, რომელიც ნორმალური გამოყენებისას გამოვლინდა;</li>
                            <li>ნივთის შეუსაბამობას საიტზე მითითებულ აღწერასთან.</li>
                        </ul>
                        <p>თუ ნაკლი ჩაბარებიდან 6 თვის განმავლობაში გამოვლინდა, ითვლება, რომ ის ნივთს მიწოდებისასაც ჰქონდა, სანამ საწინააღმდეგო არ დადასტურდება.</p>
                        <h3>რას არ მოიცავს</h3>
                        <ul>
                            <li>მექანიკურ დაზიანებას, დარტყმას და დაცემის კვალს;</li>
                            <li>სითხის, ტენის ან მტვრის მოხვედრას;</li>
                            <li>არასწორი გამოყენებით, ძაბვის ვარდნით ან შეუსაბამო დამტენით გამოწვეულ დაზიანებას;</li>
                            <li>არაავტორიზებულ შეკეთებას, გახსნას ან პროგრამულ ჩარევას;</li>
                            <li>ბუნებრივ ცვეთას და სახარჯ მასალას (მაგალითად, ფილტრები, ბატარეის ტევადობის ბუნებრივი კლება).</li>
                        </ul>
                        <h3>როგორ მივმართო</h3>
                        <ol>
                            <li>დაგვიკავშირდი და აღწერე პრობლემა. მოამზადე შეკვეთის ნომერი ან ინვოისი.</li>
                            <li>გეტყვით, სად მიიტანო ნივთი: ჩვენთან თუ მწარმოებლის ავტორიზებულ სერვის ცენტრში.</li>
                            <li>სერვის ცენტრი შეამოწმებს ნივთს და დაადგენს, საგარანტიოა თუ არა ნაკლი.</li>
                        </ol>
                        <h3>რას მიიღებ</h3>
                        <ul>
                            <li><strong>შეკეთებას</strong> უფასოდ, ნაკლის დაფიქსირებიდან არაუგვიანეს 30 დღისა;</li>
                            <li><strong>შეცვლას</strong> ახალი ნივთით, არაუგვიანეს 10 დღისა, თუ შეკეთება შეუძლებელია ან არაგონივრულად დიდ დროს მოითხოვს;</li>
                            <li><strong>ფასის შემცირებას ან თანხის დაბრუნებას</strong>, თუ ნივთის შეკეთება ან შეცვლა შეუძლებელია ან ვადაში არ შესრულდა.</li>
                        </ul>
                        <p>საგარანტიო მომსახურებასთან დაკავშირებულ ყველა ხარჯს, მათ შორის ტრანსპორტირებას, ჩვენ ვფარავთ. უმნიშვნელო ნაკლის გამო ხელშეკრულებაზე უარის თქმა შეუძლებელია, თუმცა ამ შემთხვევაშიც შეგიძლია მოითხოვო შეკეთება.</p>
                        <h3>არასწორი ან დაზიანებული ნივთი</h3>
                        <p>თუ მიიღე სხვა ნივთი, დაზიანებული ნივთი ან ისეთი, რომელიც აღწერას არ შეესაბამება, დაგვიკავშირდი. ნივთს ჩვენი ხარჯით წავიღებთ და შევცვლით ან თანხას სრულად დაგიბრუნებთ.</p>
                        HTML,
                ],
                'en' => [
                    'title' => 'Warranty and returns',
                    'intro' => 'You can return an item within 14 days without giving a reason, and factory defects are covered for 2 years.',
                    'meta_description' => 'ELIO.GE warranty and returns: 14-day returns, 2-year warranty, repair, replacement and refunds.',
                    'body' => <<<'HTML'
                        <h2>Returns within 14 days</h2>
                        <p>You can cancel an online purchase without giving a reason within 14 calendar days of receiving the item. If your order arrived in several parts, the period counts from the last item you received.</p>
                        <h3>How to return</h3>
                        <ol>
                            <li>Tell us within 14 days by phone, email or the contact form. Include the order number and which item you are returning.</li>
                            <li>Send the item back within 7 days of telling us, complete with its box, accessories, documents and any gifts that came with it.</li>
                            <li>We refund you within 14 days of your notice, to the same payment method you used, once we receive the item or proof that you have sent it.</li>
                        </ol>
                        <p>For a no-reason return you cover the cost of sending the item back. We refund the standard delivery fee.</p>
                        <p>If you used the item in a way that lowered its value (for example it is damaged or parts are missing), you may have to cover the difference. Checking and trying the item as you would in a shop does not count.</p>
                        <h3>What cannot be returned without a reason</h3>
                        <ul>
                            <li>Items costing 30 ₾ or less;</li>
                            <li>Items made or customised to your order;</li>
                            <li>Hygiene items whose sealed packaging was opened (for example earphones, shavers, toothbrushes);</li>
                            <li>Software, games and digital codes once the seal is broken or the code is activated;</li>
                            <li>Items that became inseparably mixed with other goods after delivery.</li>
                        </ul>
                        <h2>Warranty</h2>
                        <p>Every item has an official warranty. The warranty period is shown on the product page and the invoice. Whatever the manufacturer's warranty, the law gives you 2 years of cover.</p>
                        <h3>What is covered</h3>
                        <ul>
                            <li>Factory faults and defects that appear in normal use;</li>
                            <li>Items that do not match the description on the site.</li>
                        </ul>
                        <p>A fault found within 6 months of delivery is presumed to have existed at delivery unless shown otherwise.</p>
                        <h3>What is not covered</h3>
                        <ul>
                            <li>Mechanical damage, impact and drop marks;</li>
                            <li>Liquid, moisture or dust damage;</li>
                            <li>Damage from misuse, power surges or an unsuitable charger;</li>
                            <li>Unauthorised repair, opening or software tampering;</li>
                            <li>Normal wear and consumables (such as filters, or the natural loss of battery capacity).</li>
                        </ul>
                        <h3>How to claim</h3>
                        <ol>
                            <li>Contact us and describe the problem. Have your order number or invoice ready.</li>
                            <li>We tell you where to bring the item: to us or to the manufacturer's authorised service centre.</li>
                            <li>The service centre checks the item and confirms whether the fault is covered.</li>
                        </ol>
                        <h3>What you get</h3>
                        <ul>
                            <li><strong>Free repair</strong> within 30 days of the fault being recorded;</li>
                            <li><strong>Replacement</strong> with a new item within 10 days if repair is impossible or would take unreasonably long;</li>
                            <li><strong>A price reduction or refund</strong> if the item cannot be repaired or replaced, or this is not done in time.</li>
                        </ul>
                        <p>We cover every cost of warranty service, including transport. A minor fault is not grounds for cancelling the purchase, but you can still ask for a repair.</p>
                        <h3>Wrong or damaged item</h3>
                        <p>If you receive the wrong item, a damaged one or one that does not match its description, contact us. We collect it at our cost and replace it or refund you in full.</p>
                        HTML,
                ],
            ],
            'installments' => [
                'ka' => ['title' => 'განვადება', 'intro' => 'შეიძინე ახლა და გადაიხადე ნაწილ-ნაწილ — ონლაინ განაცხადით, ფილიალში მისვლის გარეშე.', 'body' => '<p>განვადება ხელმისაწვდომია საქართველოს ბანკის, TBC-ისა და Credo-ს მეშვეობით, 3-დან 24 თვემდე.</p><h3>როგორ</h3><ul><li>შეკვეთისას აირჩიე განვადება.</li><li>შეავსე ბანკის ონლაინ განაცხადი.</li><li>დამტკიცების შემდეგ შეკვეთას დაგიდასტურებთ.</li></ul>'],
                'en' => ['title' => 'Instalments', 'intro' => 'Buy now and pay in parts — apply online, no branch visit needed.', 'body' => '<p>Instalments are available through Bank of Georgia, TBC and Credo, from 3 to 24 months.</p><h3>How</h3><ul><li>Choose instalments at checkout.</li><li>Fill in the bank\'s online application.</li><li>Once approved, we confirm your order.</li></ul>'],
            ],
            'terms' => [
                'ka' => [
                    'title' => 'წესები და პირობები',
                    'intro' => 'ეს პირობები არეგულირებს ELIO.GE-ზე ნივთების შეძენას. შეკვეთის გაფორმებით ეთანხმები მათ.',
                    'meta_description' => 'ELIO.GE-ს წესები და პირობები: შეკვეთა, ფასები, გადახდა, მიწოდება, დაბრუნება და გარანტია.',
                    'body' => <<<'HTML'
                        <h3>1. ზოგადი</h3>
                        <p>ELIO.GE არის ელექტრონიკისა და საყოფაცხოვრებო ტექნიკის ონლაინ მაღაზია. საიტზე შეძენა შეუძლია 18 წელს მიღწეულ პირს. პირობები მოქმედებს საქართველოს კანონმდებლობის, მათ შორის „მომხმარებლის უფლებების დაცვის შესახებ“ კანონის შესაბამისად. თუ რომელიმე პუნქტი კანონს ეწინააღმდეგება, მოქმედებს კანონი.</p>
                        <h3>2. ინფორმაცია ნივთზე</h3>
                        <p>ვცდილობთ, ნივთის აღწერა, მახასიათებლები და ფოტოები ზუსტი იყოს. ფოტოზე ფერი შეიძლება ოდნავ განსხვავდებოდეს ეკრანის პარამეტრების გამო. თუ მიღებული ნივთი აღწერას არ შეესაბამება, შეგიძლია დააბრუნო ან შეცვალო ჩვენი ხარჯით.</p>
                        <h3>3. ფასები</h3>
                        <ul>
                            <li>ფასები მითითებულია ლარში და მოიცავს დღგ-ს.</li>
                            <li>მიწოდების საფასური შეკვეთის გაფორმებისას ცალკე ჩანს.</li>
                            <li>ფასი და მარაგი შეიძლება შეიცვალოს. შეკვეთაზე მოქმედებს ის ფასი, რომელიც შეკვეთის გაფორმებისას იყო მითითებული.</li>
                            <li>აშკარა ტექნიკური შეცდომის შემთხვევაში (მაგალითად, ფასი არასწორად ჩაიწერა) დაგიკავშირდებით. შეგიძლია შეკვეთა სწორ ფასად დაადასტურო ან გააუქმო, და გადახდილ თანხას სრულად დაგიბრუნებთ.</li>
                        </ul>
                        <h3>4. შეკვეთა</h3>
                        <ul>
                            <li>შეკვეთის გაფორმებისას მიუთითე სწორი სახელი, ტელეფონი და მისამართი. არასწორი მონაცემებით გამოწვეულ შეფერხებაზე პასუხს ვერ ვაგებთ.</li>
                            <li>შეკვეთა დადასტურებულია, როცა ონლაინ გადახდა დასრულდება ან ოპერატორი დაგიდასტურებს.</li>
                            <li>თუ ნივთი მარაგში აღარ არის, დაგიკავშირდებით და შემოგთავაზებთ ალტერნატივას ან გადახდილ თანხას დაგიბრუნებთ.</li>
                            <li>შეკვეთის გაუქმება გაგზავნამდე შეგიძლია ოპერატორთან დარეკვით.</li>
                        </ul>
                        <h3>5. გადახდა</h3>
                        <ul>
                            <li>ბარათით ონლაინ (Visa, Mastercard, Apple Pay, Google Pay). ბარათის მონაცემები მუშავდება ბანკის დაცულ გვერდზე და ჩვენ მათ არ ვინახავთ.</li>
                            <li>განვადებით ან ნაწილ-ნაწილ გადახდით: საქართველოს ბანკი, TBC, Credo. განვადების პირობებს ადგენს ბანკი.</li>
                            <li>ადგილზე გადახდით: ნაღდი ფულით ან ბარათით, კურიერთან.</li>
                            <li>საბანკო გადარიცხვით: რეკვიზიტებს ოპერატორი გამოგიგზავნის.</li>
                        </ul>
                        <h3>6. მიწოდება</h3>
                        <p>მიწოდების ვადები, ღირებულება და ჩაბარების წესი აღწერილია გვერდზე „მიწოდების პირობები“. შეკვეთა შესრულებულად ითვლება ნივთის ჩაბარებისას.</p>
                        <h3>7. დაბრუნება და გარანტია</h3>
                        <p>ნივთის დაბრუნება შეგიძლია 14 დღის განმავლობაში მიზეზის მითითების გარეშე, ხოლო ნაკლის შემთხვევაში გარანტია 2 წელი მოქმედებს. დეტალები იხილე გვერდზე „გარანტია და დაბრუნება“.</p>
                        <h3>8. ანგარიში</h3>
                        <p>ანგარიშის შექმნა სავალდებულო არ არის. თუ შექმენი, პასუხისმგებელი ხარ პაროლის დაცვაზე. ანგარიშის წაშლა შეგიძლია ჩვენთან დაკავშირებით.</p>
                        <h3>9. ფორს-მაჟორი</h3>
                        <p>მხარეები არ აგებენ პასუხს ვალდებულების შეუსრულებლობაზე, თუ ის გამოიწვია მათგან დამოუკიდებელმა გარემოებამ: სტიქიამ, ხანძარმა, საომარმა მოქმედებამ, სახელმწიფო ორგანოს გადაწყვეტილებამ და სხვა. ასეთ შემთხვევაში ვადები გარემოების ხანგრძლივობით გაგრძელდება და ამის შესახებ დაუყოვნებლივ გაცნობებთ.</p>
                        <h3>10. დავები და ცვლილებები</h3>
                        <p>საჩივრის შემთხვევაში დაგვიკავშირდი და შევეცდებით, საკითხი სწრაფად მოვაგვაროთ. თუ შეთანხმება ვერ მოხერხდა, დავა განიხილება საქართველოს კანონმდებლობით დადგენილი წესით. პირობები შეიძლება განახლდეს. ცვლილებები ძალაში შედის ამ გვერდზე გამოქვეყნებისთანავე და უკვე გაფორმებულ შეკვეთებზე არ ვრცელდება.</p>
                        HTML,
                ],
                'en' => [
                    'title' => 'Terms and conditions',
                    'intro' => 'These terms govern purchases on ELIO.GE. By placing an order you agree to them.',
                    'meta_description' => 'ELIO.GE terms and conditions: orders, prices, payment, delivery, returns and warranty.',
                    'body' => <<<'HTML'
                        <h3>1. General</h3>
                        <p>ELIO.GE is an online store for electronics and home appliances. You must be 18 or older to buy on the site. These terms follow Georgian law, including the Law on Consumer Rights Protection. Where a clause conflicts with the law, the law applies.</p>
                        <h3>2. Product information</h3>
                        <p>We aim to keep descriptions, specifications and photos accurate. Colours may look slightly different depending on your screen. If an item does not match its description, you can return or exchange it at our cost.</p>
                        <h3>3. Prices</h3>
                        <ul>
                            <li>Prices are in lari and include VAT.</li>
                            <li>The delivery fee is shown separately at checkout.</li>
                            <li>Prices and stock may change. Your order keeps the price shown when you placed it.</li>
                            <li>If a price is clearly wrong because of a technical error, we contact you. You can confirm the order at the correct price or cancel it for a full refund.</li>
                        </ul>
                        <h3>4. Orders</h3>
                        <ul>
                            <li>Give a correct name, phone number and address. We cannot be responsible for delays caused by wrong details.</li>
                            <li>An order is confirmed once online payment completes or an operator confirms it.</li>
                            <li>If an item is out of stock, we contact you to offer an alternative or refund what you paid.</li>
                            <li>You can cancel an order before it ships by calling an operator.</li>
                        </ul>
                        <h3>5. Payment</h3>
                        <ul>
                            <li>Card online (Visa, Mastercard, Apple Pay, Google Pay). Card details are handled on the bank's secure page and we never store them.</li>
                            <li>Instalments or split payment: Bank of Georgia, TBC, Credo. The bank sets the instalment terms.</li>
                            <li>Pay on delivery: cash or card to the courier.</li>
                            <li>Bank transfer: an operator sends you the bank details.</li>
                        </ul>
                        <h3>6. Delivery</h3>
                        <p>Delivery times, costs and how to receive your order are described on the Delivery page. An order is fulfilled when you receive the item.</p>
                        <h3>7. Returns and warranty</h3>
                        <p>You can return an item within 14 days without giving a reason, and defects are covered for 2 years. See the Warranty and returns page for details.</p>
                        <h3>8. Account</h3>
                        <p>You do not need an account to order. If you create one, keep your password safe. You can ask us to delete your account at any time.</p>
                        <h3>9. Force majeure</h3>
                        <p>Neither side is liable for failing to meet an obligation because of events outside its control, such as natural disasters, fire, war or government decisions. Deadlines extend for as long as the event lasts, and we tell you about it straight away.</p>
                        <h3>10. Disputes and changes</h3>
                        <p>If you have a complaint, contact us and we will try to resolve it quickly. If we cannot agree, the dispute is settled under Georgian law. We may update these terms. Changes take effect when published on this page and do not affect orders already placed.</p>
                        HTML,
                ],
            ],
            'privacy' => [
                'ka' => [
                    'title' => 'კონფიდენციალურობის პოლიტიკა',
                    'intro' => 'ვაგროვებთ მხოლოდ იმ მონაცემებს, რაც შეკვეთის შესასრულებლად და საიტის გასაუმჯობესებლად გვჭირდება, და არასდროს ვყიდით მათ.',
                    'meta_description' => 'როგორ აგროვებს, იყენებს და იცავს ELIO.GE მომხმარებლის პერსონალურ მონაცემებს.',
                    'body' => <<<'HTML'
                        <p>ეს პოლიტიკა აღწერს, როგორ ამუშავებს ELIO.GE მომხმარებლის პერსონალურ მონაცემებს „პერსონალურ მონაცემთა დაცვის შესახებ“ საქართველოს კანონის შესაბამისად.</p>
                        <h3>რა მონაცემებს ვაგროვებთ</h3>
                        <ul>
                            <li><strong>შეკვეთისას:</strong> სახელს, გვარს, ტელეფონს, ელ-ფოსტას, მიწოდების ქალაქსა და მისამართს, შეკვეთის კომენტარს.</li>
                            <li><strong>ანგარიშის შექმნისას:</strong> სახელს, ტელეფონს ან ელ-ფოსტას, პაროლს დაშიფრული სახით, შენახულ მისამართებს და რჩეულებს.</li>
                            <li><strong>შეტყობინებებისთვის:</strong> ტელეფონს ან ელ-ფოსტას, თუ გამოიწერე ფასის კლების შეტყობინება.</li>
                            <li><strong>ავტომატურად:</strong> IP მისამართს, ბრაუზრისა და მოწყობილობის ტიპს, ნანახ გვერდებს და ქუქი-ფაილებს.</li>
                        </ul>
                        <p>ბარათის მონაცემებს ჩვენ ვერ ვხედავთ და არ ვინახავთ. ისინი მუშავდება უშუალოდ ბანკის დაცულ გვერდზე.</p>
                        <h3>რისთვის ვიყენებთ</h3>
                        <ul>
                            <li>შეკვეთის დასადასტურებლად, გადახდისა და მიწოდებისთვის;</li>
                            <li>შეკვეთის სტატუსის შესახებ SMS-ისა და ელ-ფოსტის გასაგზავნად;</li>
                            <li>გარანტიის, დაბრუნებისა და კითხვების განსახილველად;</li>
                            <li>თუ შეკვეთა არ დაასრულე, შესაძლოა დაგიკავშირდეთ დახმარების შესათავაზებლად;</li>
                            <li>ფასის კლების შეტყობინების გასაგზავნად, თუ ის გამოიწერე;</li>
                            <li>საიტის მუშაობის გასაუმჯობესებლად და, შენი თანხმობით, რეკლამის საჩვენებლად;</li>
                            <li>კანონით დაკისრებული ვალდებულებების შესასრულებლად, მაგალითად, საბუღალტრო აღრიცხვისთვის.</li>
                        </ul>
                        <h3>სამართლებრივი საფუძველი</h3>
                        <p>მონაცემებს ვამუშავებთ, როცა ეს საჭიროა შენთან დადებული ხელშეკრულების შესასრულებლად, კანონით დაკისრებული მოვალეობისთვის, ჩვენი კანონიერი ინტერესისთვის (მაგალითად, თაღლითობის პრევენცია) ან შენი თანხმობით (მაგალითად, სარეკლამო ქუქი-ფაილები და შეთავაზებები).</p>
                        <h3>ვის ვუზიარებთ</h3>
                        <p>მონაცემებს არ ვყიდით და არ ვაქირავებთ. ვუზიარებთ მხოლოდ იმ მოცულობით, რაც სერვისისთვის აუცილებელია:</p>
                        <ul>
                            <li>საკურიერო კომპანიას, მიწოდებისთვის;</li>
                            <li>ბანკებსა და გადახდის სისტემებს, გადახდისა და განვადებისთვის;</li>
                            <li>SMS-ისა და ელ-ფოსტის გაგზავნის სერვისებს;</li>
                            <li>ანალიტიკისა და რეკლამის სერვისებს (მაგალითად, Google, Meta), მხოლოდ შენი თანხმობით;</li>
                            <li>სახელმწიფო ორგანოებს, თუ ამას კანონი მოითხოვს.</li>
                        </ul>
                        <h3>ქუქი-ფაილები</h3>
                        <p>აუცილებელი ქუქი-ფაილები საჭიროა კალათის, ავტორიზაციისა და ენის შესანახად. ანალიტიკური და სარეკლამო ქუქი-ფაილები ჩაირთვება მხოლოდ შენი თანხმობით, რომლის შეცვლაც ნებისმიერ დროს შეგიძლია საიტის ქვედა ნაწილში არსებული ქუქი-ფაილების პარამეტრებიდან.</p>
                        <h3>შენახვის ვადა</h3>
                        <ul>
                            <li>შეკვეთის მონაცემებს ვინახავთ საგადასახადო და საბუღალტრო კანონმდებლობით დადგენილი ვადით, ჩვეულებრივ 6 წლამდე;</li>
                            <li>დაუსრულებელი შეკვეთების მონაცემებს და ფასის შეტყობინებებს ავტომატურად ვშლით გარკვეული პერიოდის შემდეგ;</li>
                            <li>ანგარიშის მონაცემებს ვინახავთ, სანამ ანგარიში აქტიურია.</li>
                        </ul>
                        <h3>უსაფრთხოება</h3>
                        <p>საიტი მუშაობს დაშიფრული კავშირით (HTTPS), პაროლები ინახება დაშიფრული სახით, ხოლო მონაცემებზე წვდომა აქვთ მხოლოდ იმ თანამშრომლებს, ვისაც ეს სამუშაოსთვის სჭირდება.</p>
                        <h3>შენი უფლებები</h3>
                        <p>შეგიძლია მოითხოვო ინფორმაცია იმაზე, რა მონაცემებს ვამუშავებთ შენ შესახებ, მათი გასწორება, წაშლა ან დამუშავების შეზღუდვა, ასევე გამოიხმო თანხმობა და უარი თქვა სარეკლამო შეტყობინებებზე. ამისთვის დაგვიკავშირდი კონტაქტის გვერდზე მითითებული არხებით. მოთხოვნას ვუპასუხებთ კანონით დადგენილ ვადაში. თუ ფიქრობ, რომ უფლება დაგერღვა, შეგიძლია მიმართო პერსონალურ მონაცემთა დაცვის სამსახურს.</p>
                        <h3>არასრულწლოვნები</h3>
                        <p>საიტი 18 წლამდე პირებისთვის არ არის განკუთვნილი და მათ მონაცემებს შეგნებულად არ ვაგროვებთ.</p>
                        <h3>ცვლილებები</h3>
                        <p>პოლიტიკა შეიძლება განახლდეს. განახლების თარიღი ჩანს გვერდის ზედა ნაწილში.</p>
                        HTML,
                ],
                'en' => [
                    'title' => 'Privacy policy',
                    'intro' => 'We collect only the data we need to fulfil your order and improve the site, and we never sell it.',
                    'meta_description' => 'How ELIO.GE collects, uses and protects customers\' personal data.',
                    'body' => <<<'HTML'
                        <p>This policy explains how ELIO.GE processes personal data under the Law of Georgia on Personal Data Protection.</p>
                        <h3>What we collect</h3>
                        <ul>
                            <li><strong>When you order:</strong> your name, phone, email, delivery city and address, and order notes.</li>
                            <li><strong>When you create an account:</strong> your name, phone or email, an encrypted password, saved addresses and favourites.</li>
                            <li><strong>For alerts:</strong> your phone or email if you subscribe to a price-drop alert.</li>
                            <li><strong>Automatically:</strong> IP address, browser and device type, pages viewed, and cookies.</li>
                        </ul>
                        <p>We never see or store your card details. They are handled directly on the bank's secure page.</p>
                        <h3>How we use it</h3>
                        <ul>
                            <li>To confirm, take payment for and deliver your order;</li>
                            <li>To send order status updates by SMS and email;</li>
                            <li>To handle warranty claims, returns and questions;</li>
                            <li>If you leave an order unfinished, we may contact you to offer help;</li>
                            <li>To send price-drop alerts you subscribed to;</li>
                            <li>To improve the site and, with your consent, show advertising;</li>
                            <li>To meet legal obligations such as accounting.</li>
                        </ul>
                        <h3>Legal basis</h3>
                        <p>We process data when needed to perform our contract with you, to meet a legal duty, for our legitimate interests (such as preventing fraud), or with your consent (such as advertising cookies and offers).</p>
                        <h3>Who we share it with</h3>
                        <p>We do not sell or rent your data. We share it only as far as the service requires:</p>
                        <ul>
                            <li>Courier companies, for delivery;</li>
                            <li>Banks and payment providers, for payments and instalments;</li>
                            <li>SMS and email providers;</li>
                            <li>Analytics and advertising services (such as Google and Meta), only with your consent;</li>
                            <li>Public authorities, where the law requires it.</li>
                        </ul>
                        <h3>Cookies</h3>
                        <p>Essential cookies keep your cart, sign-in and language. Analytics and advertising cookies are used only with your consent, which you can change at any time in the cookie settings at the bottom of the site.</p>
                        <h3>How long we keep it</h3>
                        <ul>
                            <li>Order data for as long as tax and accounting law requires, usually up to 6 years;</li>
                            <li>Unfinished order data and price alerts are deleted automatically after a set period;</li>
                            <li>Account data for as long as your account is active.</li>
                        </ul>
                        <h3>Security</h3>
                        <p>The site uses an encrypted connection (HTTPS), passwords are stored encrypted, and only staff who need the data for their work can access it.</p>
                        <h3>Your rights</h3>
                        <p>You can ask what data we hold about you, and ask us to correct, delete or restrict it. You can also withdraw consent and opt out of marketing. Contact us through the channels on the contact page; we respond within the time the law sets. If you believe your rights were breached, you can contact the Personal Data Protection Service.</p>
                        <h3>Children</h3>
                        <p>The site is not meant for people under 18, and we do not knowingly collect their data.</p>
                        <h3>Changes</h3>
                        <p>We may update this policy. The date of the last update is shown at the top of the page.</p>
                        HTML,
                ],
            ],
        ];
    }
}
