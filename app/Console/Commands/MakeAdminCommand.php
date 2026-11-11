<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('elio:admin {email} {--revoke}')]
#[Description('Grant or revoke admin panel access for an existing user')]
class MakeAdminCommand extends Command
{
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $isAdmin = ! $this->option('revoke');
        $user->forceFill(['is_admin' => $isAdmin])->save();

        $this->info($isAdmin ? "{$user->email} can now open the admin panel." : "{$user->email} no longer has admin access.");

        return self::SUCCESS;
    }
}
