<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\Facebook\Pixel;
use App\Services\Payments\Drivers\BogInstallment;
use App\Services\Payments\Drivers\CredoInstallment;
use App\Services\Payments\Drivers\CredoSplit;
use App\Services\Payments\Drivers\Flitt;
use App\Services\Payments\Drivers\TbcInstallment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PaymentManager
{
    public const DRIVERS = [
        Flitt::NAME => Flitt::class,
        'bog-installment' => BogInstallment::class,
        'tbc-installment' => TbcInstallment::class,
        'credo-installment' => CredoInstallment::class,
        'credo-split' => CredoSplit::class,
    ];

    public function driver(string $name): PaymentDriver
    {
        $class = self::DRIVERS[$name] ?? null;

        if (! $class) {
            throw new RuntimeException("No payment driver registered for [{$name}].");
        }

        return app($class);
    }

    public function has(?string $name): bool
    {
        return $name !== null && isset(self::DRIVERS[$name]);
    }

    public function isReady(?string $name): bool
    {
        return $this->has($name) && $this->driver($name)->isConfigured();
    }

    public function start(Order $order, string $driver, array $options = []): PaymentTransaction
    {
        $reusable = DB::transaction(function () use ($order, $driver, $options): ?PaymentTransaction {
            $current = Order::query()->lockForUpdate()->findOrFail($order->id);
            $method = $current->paymentMethod;

            if ($current->isPaid() || $current->status === 'cancelled') {
                throw new RuntimeException(__('payments.already_paid'));
            }

            if (! $method || ! $method->is_active || $method->driver !== $driver || ! $method->fitsTotal((float) $current->total)) {
                throw new RuntimeException(__('payments.unavailable'));
            }

            $pending = $current->transactions()->where('status', PaymentTransaction::PENDING)->latest('id')->first();

            if ($pending && $pending->driver === $driver && $this->usableRedirect($pending) && $this->sameOptions($pending, $options)) {
                return $pending;
            }

            return null;
        });

        if ($reusable) {
            return $reusable;
        }

        $this->releasePending($order);

        if ($order->fresh()->isPaid()) {
            throw new RuntimeException(__('payments.already_paid'));
        }

        $transaction = $this->driver($driver)->start($order->fresh(['items.product.kaTranslation', 'items.product.coverImage']), $options);

        if (! $transaction->redirect_url) {
            throw new RuntimeException(__('payments.failed'));
        }

        $order->refresh();

        if (in_array($order->payment_status, [Order::PAYMENT_PENDING, Order::PAYMENT_FAILED], true)) {
            $order->update(['payment_status' => Order::PAYMENT_PROCESSING]);
        }

        $order->log('payment', __('payments.started', ['driver' => $this->label($driver)]));

        return $transaction;
    }

    public function settle(PaymentTransaction $transaction, bool $paid): void
    {
        $order = $transaction->order;

        if (! $order) {
            return;
        }

        $settled = false;

        DB::transaction(function () use ($transaction, $order, $paid, &$settled): void {
            $current = PaymentTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            $currentOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $paid) {
                if ($current->status === PaymentTransaction::SUCCESS) {
                    return;
                }

                if ($current->status === PaymentTransaction::PENDING) {
                    $current->update(['checked_at' => now()]);

                    return;
                }

                if ($currentOrder->payment_status === Order::PAYMENT_PROCESSING
                    && ! $currentOrder->transactions()->where('status', PaymentTransaction::PENDING)->exists()) {
                    $currentOrder->update(['payment_status' => Order::PAYMENT_FAILED]);
                    $currentOrder->log('payment', __('payments.refused', ['driver' => $this->label($current->driver)]), Order::PAYMENT_PROCESSING, Order::PAYMENT_FAILED);
                }

                return;
            }

            $current->update([
                'status' => PaymentTransaction::SUCCESS,
                'checked_at' => now(),
                'paid_at' => $current->paid_at ?? now(),
            ]);

            if ($currentOrder->isPaid()) {
                return;
            }

            $from = $currentOrder->payment_status;

            $currentOrder->update([
                'payment_status' => Order::PAYMENT_PAID,
                'paid_at' => now(),
                'installment_months' => $current->months,
            ]);

            $currentOrder->log('payment', __('payments.paid_via', ['driver' => $this->label($current->driver)]), $from, Order::PAYMENT_PAID);
            $settled = true;
        });

        if ($settled) {
            Log::channel('payments')->info('payment settled', [
                'order' => $order->number,
                'driver' => $transaction->driver,
                'transaction' => $transaction->id,
            ]);

            app(Pixel::class)->reportPurchase($order->fresh('items'));
        }
    }

    public function label(string $driver): string
    {
        return __("payments.drivers.$driver");
    }

    public static function driverOptions(): array
    {
        return collect(self::DRIVERS)->keys()->mapWithKeys(fn (string $driver) => [$driver => __("payments.drivers.$driver")])->all();
    }

    protected function releasePending(Order $order): void
    {
        $pending = $order->transactions()->where('status', PaymentTransaction::PENDING)->get();

        foreach ($pending as $transaction) {
            try {
                if ($this->has($transaction->driver) && $this->driver($transaction->driver)->confirm($transaction)) {
                    $this->settle($transaction, true);

                    continue;
                }
            } catch (Throwable $e) {
                Log::channel('payments')->warning('pending payment could not be asked about', [
                    'order' => $order->number,
                    'transaction' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($transaction->fresh()->isPending()) {
                $transaction->update(['status' => PaymentTransaction::CANCELLED, 'checked_at' => now()]);
            }
        }
    }

    protected function sameOptions(PaymentTransaction $transaction, array $options): bool
    {
        if (! isset($options['months'])) {
            return true;
        }

        return (int) $transaction->months === (int) $options['months']
            && data_get($transaction->request, 'installment_type', $options['type'] ?? null) === ($options['type'] ?? null);
    }

    protected function usableRedirect(PaymentTransaction $transaction): bool
    {
        if (! $transaction->redirect_url || $transaction->created_at->lt(now()->subMinutes(15))) {
            return false;
        }

        parse_str((string) parse_url($transaction->redirect_url, PHP_URL_QUERY), $query);

        return ! isset($query['expires']) || (int) $query['expires'] > time();
    }
}
