<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Services\Payments\Drivers\BogInstallment;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['bog_card', 'bog-card', null, '/img/payments/bog-1.png', null,
                ['ka' => 'საქართველოს ბანკის ონლაინ გადახდა', 'en' => 'Bank of Georgia — card payment'],
                ['ka' => 'Visa, Mastercard, Apple Pay, Google Pay', 'en' => 'Visa, Mastercard, Apple Pay, Google Pay']],
            ['bog_installment', 'bog-installment', BogInstallment::STANDARD, '/img/payments/bog-1.png', 300,
                ['ka' => 'საქართველოს ბანკის ონლაინ განვადება', 'en' => 'Bank of Georgia — instalments'],
                ['ka' => '3-დან 24 თვემდე, ონლაინ განაცხადით', 'en' => '3 to 24 months, apply online']],
            ['bog_split', 'bog-installment', BogInstallment::ZERO, '/img/payments/bog-2.png', 100,
                ['ka' => 'საქართველოს ბანკის ნაწილ-ნაწილ', 'en' => 'Bank of Georgia — split payment'],
                ['ka' => 'ნაწილ-ნაწილ გადახდა პროცენტის გარეშე', 'en' => 'Pay in parts with no interest']],
            ['tbc_installment', 'tbc-installment', null, '/img/payments/tbc-1.png', 300,
                ['ka' => 'TBC ბანკის ონლაინ განვადება', 'en' => 'TBC Bank — instalments'],
                ['ka' => 'განაცხადი TBC-ის გვერდზე', 'en' => 'Apply on the TBC page']],
            ['credo_installment', 'credo-installment', null, '/img/payments/credo-1.png', 300,
                ['ka' => 'Credo ონლაინ განვადება', 'en' => 'Credo — instalments'],
                ['ka' => 'განაცხადი Credo-ს გვერდზე', 'en' => 'Apply on the Credo page']],
            ['credo_split', 'credo-split', null, '/img/payments/credo-2.png', 100,
                ['ka' => 'Credo ნაწილ-ნაწილ', 'en' => 'Credo Split'],
                ['ka' => 'ნაწილ-ნაწილ გადახდა Credo-სთან', 'en' => 'Pay in parts with Credo']],
            ['cash', null, null, null, null,
                ['ka' => 'ადგილზე გადახდა', 'en' => 'Cash on delivery'],
                ['ka' => 'ნაღდით ან ბარათით კურიერთან', 'en' => 'Cash or card to the courier']],
            ['transfer', null, null, null, null,
                ['ka' => 'საბანკო გადარიცხვა', 'en' => 'Bank transfer'],
                ['ka' => 'რეკვიზიტებს ოპერატორი გამოგიგზავნით', 'en' => 'An operator will send the bank details']],
        ];

        foreach ($methods as $index => [$code, $driver, $type, $logo, $minimum, $names, $notes]) {
            $method = PaymentMethod::query()->firstOrCreate(['code' => $code], [
                'driver' => $driver,
                'installment_type' => $type,
                'logo' => $logo,
                'is_active' => true,
                'is_online' => $driver !== null,
                'on_quick_order' => true,
                'min_total' => $minimum,
                'sort_order' => $index + 1,
            ]);

            if ($method->wasRecentlyCreated) {
                $method->saveTranslations([
                    'ka' => ['name' => $names['ka'], 'note' => $notes['ka']],
                    'en' => ['name' => $names['en'], 'note' => $notes['en']],
                ]);
            }
        }
    }
}
