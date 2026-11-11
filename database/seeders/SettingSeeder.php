<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            'site_name' => 'ELIO.GE',
            'phone' => '032 2 00 00 00',
            'hotline' => '*0000',
            'email' => 'info@elio.ge',
            'hours_week' => '10:00–20:00',
            'hours_sunday' => '11:00–18:00',
        ])->each(fn (string $value, string $key) => Setting::put($key, $value));
    }
}
