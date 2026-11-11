<?php

namespace App\Services\Payments\Drivers;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\Payments\PaymentDriver;
use App\Services\Payments\PaymentLines;
use App\Support\Redact;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

class CredoInstallment implements PaymentDriver
{
    public const FINISHED = 5;

    public const SIGNED = 12;

    public const REJECTED = 6;

    public const CANCELLED = 7;

    public function configKey(): string
    {
        return 'credo';
    }

    public function driverName(): string
    {
        return 'credo-installment';
    }

    public function formRoute(): string
    {
        return 'payment.credo.form';
    }

    public function isConfigured(): bool
    {
        return filled($this->shopId());
    }

    public function shopId(): string
    {
        return (string) config($this->configKey().'.merchant_id');
    }

    public function widgetUrl(): string
    {
        return (string) config($this->configKey().'.widget_url');
    }

    public function fieldName(): string
    {
        return (string) config($this->configKey().'.field', 'credoinstallment');
    }

    public function label(): string
    {
        return (string) config($this->configKey().'.label', 'Credo');
    }

    public function statusUrl(): string
    {
        return (string) config($this->configKey().'.status_url');
    }

    protected function fee(): float
    {
        return (float) config($this->configKey().'.fee', 0.10);
    }

    protected function secret(): string
    {
        return (string) config($this->configKey().'.secret');
    }

    public function hashFor(string $orderCode): string
    {
        return md5($this->shopId().$orderCode.$this->secret());
    }

    public function start(Order $order, array $options = []): PaymentTransaction
    {
        return PaymentTransaction::query()->create([
            'order_id' => $order->id,
            'driver' => $this->driverName(),
            'external_id' => (string) $order->number,
            'amount' => $this->total($order),
            'status' => PaymentTransaction::PENDING,
            'request' => $this->payload($order),
            'redirect_url' => URL::temporarySignedRoute($this->formRoute(), now()->addHours(6), ['number' => $order->number]),
        ]);
    }

    public function confirm(PaymentTransaction $transaction): bool
    {
        if (! $this->shopId() || ! $this->secret()) {
            Log::channel('payments')->info('credo cannot be asked, so nothing is confirmed here', [
                'driver' => $this->driverName(),
                'order' => $transaction->external_id,
                'missing' => ! $this->shopId() ? 'merchant id' : 'secret',
            ]);

            return false;
        }

        $code = (string) $transaction->external_id;

        try {
            $response = Http::timeout((int) config($this->configKey().'.timeout', 20))
                ->get($this->statusUrl(), [
                    'merchantId' => $this->shopId(),
                    'orderCode' => $code,
                    'hash' => $this->hashFor($code),
                ]);
        } catch (Throwable $e) {
            Log::channel('payments')->warning('credo status unreachable', [
                'order' => $code,
                'error' => Redact::secrets($e->getMessage()),
            ]);

            return false;
        }

        $body = $response->json() ?? [];

        if (($body['status'] ?? null) !== 200) {
            Log::channel('payments')->info('credo has no verdict yet', [
                'order' => $code,
                'status' => $body['status'] ?? $response->status(),
                'message' => $body['message'] ?? null,
            ]);

            return false;
        }

        return $this->readStatus($transaction, (int) ($body['data'] ?? 0), $body);
    }

    protected function readStatus(PaymentTransaction $transaction, int $status, array $body): bool
    {
        $transaction->update([
            'response' => array_merge((array) $transaction->response, ['status' => $body]),
            'checked_at' => now(),
        ]);

        if (in_array($status, [self::REJECTED, self::CANCELLED], true)) {
            $transaction->update(['status' => PaymentTransaction::CANCELLED]);

            return false;
        }

        if ($status === self::SIGNED) {
            $this->noteSigned($transaction);
        }

        return $status === self::FINISHED;
    }

    protected function noteSigned(PaymentTransaction $transaction): void
    {
        $order = $transaction->order;
        $note = __('payments.credo_signed');

        if (! $order || $order->events()->where('type', 'payment')->where('note', $note)->exists()) {
            return;
        }

        $order->log('payment', $note);
    }

    public function handleCallback(array $payload): ?PaymentTransaction
    {
        $code = $payload['orderCode'] ?? $payload['order_code'] ?? null;

        if (! $code) {
            return null;
        }

        $transaction = PaymentTransaction::query()
            ->where('driver', $this->driverName())
            ->where('external_id', $code)
            ->first();

        $transaction?->update([
            'response' => array_merge((array) $transaction->response, ['callback' => $payload]),
        ]);

        return $transaction;
    }

    public function payload(Order $order): array
    {
        $fee = $this->fee();

        $products = array_map(fn (array $line) => [
            'id' => (string) $line['item']->product_id,
            'title' => mb_substr($line['item']->name, 0, 100),
            'amount' => $line['qty'],
            'price' => (int) round(($line['price'] * (1 + $fee)) * 100),
            'type' => '0',
        ], app(PaymentLines::class)->of($order));

        return [
            'merchantId' => $this->shopId(),
            'orderCode' => (string) $order->number,
            'check' => $this->checksum($products),
            'products' => $products,
        ];
    }

    protected function checksum(array $products): string
    {
        $string = '';
        $secret = $this->secret();

        foreach ($products as $product) {
            $string .= $product['id'].$product['title'].$product['amount'].$product['price'].$product['type'].$secret;
        }

        return md5($string);
    }

    public function total(Order $order): float
    {
        return round((float) $order->total * (1 + $this->fee()), 2);
    }
}
