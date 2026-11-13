<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        Faq::query()->delete();

        collect([
            [
                'icon' => 'delivery',
                'question' => ['ka' => 'მიწოდება — ვადები და ფასი', 'en' => 'Delivery — times and prices'],
                'answer' => ['ka' => 'მიწოდება ხორციელდება მთელ საქართველოში: თბილისში — 2 სამუშაო დღეში, რეგიონებში — 3–7 სამუშაო დღეში.', 'en' => 'We deliver across Georgia: Tbilisi within 2 business days, the regions within 3–7 business days.'],
            ],
            [
                'icon' => 'warranty',
                'question' => ['ka' => 'გარანტია', 'en' => 'Warranty'],
                'answer' => ['ka' => 'ყველა პროდუქტს აქვს ოფიციალური გარანტია 2 წლამდე.', 'en' => 'Every product has an official warranty of up to 2 years.'],
            ],
            [
                'icon' => 'installments',
                'question' => ['ka' => 'განვადება — ბანკები და პირობები', 'en' => 'Installments — banks and terms'],
                'answer' => ['ka' => 'განვადება ხელმისაწვდომია საქართველოს, თიბისი და კრედო ბანკებით, 6-დან 24 თვემდე. განაცხადის შევსებას 5 წუთი სჭირდება.', 'en' => 'Installments are available with Bank of Georgia, TBC and Credo, from 6 to 24 months. Applying takes about 5 minutes.'],
            ],
            [
                'icon' => 'payment',
                'question' => ['ka' => 'გადახდის მეთოდები', 'en' => 'Payment methods'],
                'answer' => ['ka' => 'შეგიძლია გადაიხადო ბარათით ონლაინ (Visa, Mastercard, Apple Pay, Google Pay), განვადებით ან ნაღდი ფულით კურიერთან.', 'en' => 'You can pay by card online (Visa, Mastercard, Apple Pay, Google Pay), in installments, or in cash to the courier.'],
            ],
        ])->each(fn (array $faq, int $index) => Faq::query()->create([...$faq, 'position' => $index + 1]));
    }
}
