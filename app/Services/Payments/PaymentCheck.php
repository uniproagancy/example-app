<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Support\Redact;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentCheck
{
    public function __construct(protected PaymentManager $payments) {}

    public function ask(Order $order): array
    {
        $transaction = $order->transactions()
            ->whereIn('status', [PaymentTransaction::PENDING, PaymentTransaction::FAILED])
            ->latest('id')
            ->first();

        if (! $transaction) {
            return $this->no(__('payments.check.no_transaction'));
        }

        if (! $this->payments->has($transaction->driver)) {
            return $this->no(__('payments.check.no_driver', ['driver' => $transaction->driver]));
        }

        try {
            $paid = $this->payments->driver($transaction->driver)->confirm($transaction);
        } catch (Throwable $e) {
            Log::channel('payments')->warning('manual check failed', [
                'order' => $order->number,
                'driver' => $transaction->driver,
                'error' => Redact::secrets($e->getMessage()),
            ]);

            return $this->no(__('payments.check.unreachable'));
        }

        $this->payments->settle($transaction, $paid);

        if ($paid) {
            return ['paid' => true, 'message' => __('payments.check.confirmed'), 'type' => 'success'];
        }

        $transaction->refresh();

        if (in_array($transaction->status, [PaymentTransaction::CANCELLED, PaymentTransaction::FAILED], true)) {
            return $this->no(__('payments.check.refused'));
        }

        return $this->no(__('payments.check.not_yet'));
    }

    protected function no(string $message): array
    {
        return ['paid' => false, 'message' => $message, 'type' => 'warning'];
    }
}
