<?php

namespace Database\Seeders;

use App\Models\PromoCode;
use Illuminate\Database\Seeder;

class PromoCodeSeeder extends Seeder
{
    public function run(): void
    {
        PromoCode::query()->updateOrCreate(['code' => 'ELIO10'], ['percent' => 10, 'is_active' => true]);
    }
}
