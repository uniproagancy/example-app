<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class LocalTestUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isLocal()) {
            return;
        }

        $user = User::query()->updateOrCreate(
            ['email' => 'test@elio.test'],
            ['name' => 'ნინო ბერიძე', 'phone' => '+995 555 12 34 56', 'password' => 'elio-local-test'],
        );

        User::query()->updateOrCreate(
            ['email' => 'admin@elio.test'],
            ['name' => 'ELIO ადმინი', 'password' => 'elio-local-admin'],
        )->forceFill(['is_admin' => true])->save();

        if ($user->addresses()->doesntExist()) {
            $user->addresses()->create(['title' => 'სახლი', 'line' => 'თბილისი, ჭავჭავაძის გამზ. 21, ბინა 14', 'is_default' => true]);
        }
    }
}
