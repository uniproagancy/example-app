<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Services\Payments\Drivers\BogInstallment;
use App\Support\Catalog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

class BankHandoff
{
    public function __construct(protected PaymentManager $payments) {}

    public function begin(Order $order): array
    {
        $method = $order->paymentMethod;

        if (! $method || ! $method->isPayableOnline()) {
            return ['type' => 'none'];
        }

        if ($order->isPaid()) {
            return ['type' => 'error', 'message' => __('payments.already_paid')];
        }

        if (! $method->fitsTotal((float) $order->total)) {
            return ['type' => 'error', 'message' => __('payments.below_minimum', ['amount' => Catalog::money((float) $method->min_total)])];
        }

        if ($method->driver === 'bog-installment') {
            $type = $method->installment_type ?: BogInstallment::STANDARD;

            return [
                'type' => 'calculator',
                'bnpl' => $type === BogInstallment::ZERO,
                'amount' => BogInstallment::amountFor($order, $type),
                'url' => URL::temporarySignedRoute('payment.bog.installment', now()->addHours(6), ['number' => $order->number]),
            ];
        }

        try {
            return ['type' => 'redirect', 'url' => $this->payments->start($order, $method->driver)->redirect_url];
        } catch (Throwable $e) {
            Log::channel('payments')->error('payment could not be started', [
                'order' => $order->number,
                'driver' => $method->driver,
                'error' => $e->getMessage(),
            ]);

            $order->log('payment', __('payments.start_failed', ['reason' => mb_substr($e->getMessage(), 0, 300)]));

            return ['type' => 'error', 'message' => __('payments.failed')];
        }
    }
}
