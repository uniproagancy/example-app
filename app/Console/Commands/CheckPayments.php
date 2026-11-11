<?php

namespace App\Console\Commands;

use App\Models\PaymentTransaction;
use App\Services\Payments\PaymentManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('payments:check {--hours=168 : How far back to look, in hours}')]
#[Description('Ask the banks about pending payments and settle the confirmed ones')]
class CheckPayments extends Command
{
    public function handle(PaymentManager $payments): int
    {
        $query = PaymentTransaction::query()
            ->where('status', PaymentTransaction::PENDING)
            ->where('created_at', '>=', now()->subHours(max(1, (int) $this->option('hours'))))
            ->with('order');

        $waiting = (clone $query)->count();

        if (! $waiting) {
            $this->info('Nothing pending.');

            return self::SUCCESS;
        }

        $paid = 0;

        foreach ($query->lazyById(200) as $transaction) {
            if (! $payments->has($transaction->driver)) {
                continue;
            }

            try {
                $confirmed = $payments->driver($transaction->driver)->confirm($transaction);
                $payments->settle($transaction, $confirmed);
                $paid += (int) $confirmed;
            } catch (Throwable $e) {
                $this->warn("#{$transaction->id}: {$e->getMessage()}");
            }
        }

        $this->info("{$waiting} checked, {$paid} confirmed.");

        return self::SUCCESS;
    }
}
