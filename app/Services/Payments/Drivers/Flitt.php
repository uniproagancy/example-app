<?php

namespace App\Services\Payments\Drivers;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\Payments\PaymentDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

class Flitt implements PaymentDriver
{
    public const NAME = 'flitt';

    public function isConfigured(): bool
    {
        return filled(config('services.flitt.merchant_id')) && filled(config('services.flitt.secret_key'));
    }

    public function start(Order $order, array $options = []): PaymentTransaction
    {
        $request = $this->sign([
            'version' => '1.0.1',
            'merchant_id' => (int) config('services.flitt.merchant_id'),
            'order_id' => $order->number.'-'.Str::lower(Str::random(6)),
            'order_desc' => __('payments.flitt_description', ['number' => $order->number]),
            'amount' => $this->minorUnits((float) $order->total),
            'currency' => 'GEL',
            'lang' => 'ka',
            'sender_email' => $order->email,
            'response_url' => URL::temporarySignedRoute('payment.return', now()->addHours(6), ['number' => $order->number]),
            'server_callback_url' => route('payment.callback', ['driver' => self::NAME]),
        ]);

        $response = Http::acceptJson()->timeout(30)->post($this->url('checkout/url'), ['request' => $request]);
        $data = (array) $response->json('response', []);

        if (! $response->successful() || ($data['response_status'] ?? null) !== 'success' || empty($data['checkout_url'])) {
            Log::channel('payments')->error('flitt refused the payment', [
                'order' => $order->number,
                'status' => $response->status(),
                'error' => $data['error_message'] ?? null,
                'code' => $data['error_code'] ?? null,
            ]);

            throw new RuntimeException(__('payments.failed'));
        }

        return PaymentTransaction::query()->create([
            'order_id' => $order->id,
            'driver' => self::NAME,
            'external_id' => $request['order_id'],
            'amount' => $order->total,
            'status' => PaymentTransaction::PENDING,
            'request' => array_diff_key($request, ['signature' => true]),
            'response' => $data,
            'redirect_url' => $data['checkout_url'],
        ]);
    }

    public function confirm(PaymentTransaction $transaction): bool
    {
        if (! $transaction->external_id) {
            return false;
        }

        $response = Http::acceptJson()->timeout(20)->post($this->url('status/order_id'), ['request' => $this->sign([
            'version' => '1.0.1',
            'merchant_id' => (int) config('services.flitt.merchant_id'),
            'order_id' => $transaction->external_id,
        ])]);

        $data = (array) $response->json('response', []);

        if (! $response->successful() || ($data['response_status'] ?? null) !== 'success') {
            Log::channel('payments')->warning('flitt status unavailable', [
                'transaction' => $transaction->id,
                'status' => $response->status(),
                'error' => $data['error_message'] ?? null,
            ]);

            return false;
        }

        if (! $this->hasValidSignature($data)) {
            Log::channel('payments')->error('flitt status has a wrong signature', ['transaction' => $transaction->id]);

            return false;
        }

        $transaction->update([
            'response' => array_merge((array) $transaction->response, ['status' => $data]),
            'checked_at' => now(),
        ]);

        if ((string) ($data['order_id'] ?? '') !== (string) $transaction->external_id
            || (int) ($data['amount'] ?? 0) !== $this->minorUnits((float) $transaction->amount)) {
            Log::channel('payments')->error('flitt status does not match the payment', [
                'transaction' => $transaction->id,
                'order_id' => $data['order_id'] ?? null,
                'amount' => $data['amount'] ?? null,
            ]);

            return false;
        }

        $status = (string) ($data['order_status'] ?? '');

        if (in_array($status, ['declined', 'expired', 'reversed'], true)) {
            $transaction->update(['status' => PaymentTransaction::FAILED]);

            return false;
        }

        return $status === 'approved';
    }

    public function handleCallback(array $payload): ?PaymentTransaction
    {
        $data = isset($payload['response']) && is_array($payload['response']) ? $payload['response'] : $payload;

        if (empty($data['order_id']) || ! $this->hasValidSignature($data)) {
            Log::channel('payments')->warning('flitt callback rejected', ['order_id' => $data['order_id'] ?? null]);

            return null;
        }

        $transaction = PaymentTransaction::query()
            ->where('driver', self::NAME)
            ->where('external_id', $data['order_id'])
            ->first();

        $transaction?->update([
            'response' => array_merge((array) $transaction->response, ['callback' => $data]),
        ]);

        return $transaction;
    }

    public function signature(array $params): string
    {
        $values = collect($params)
            ->except(['signature', 'response_signature_string'])
            ->reject(fn (mixed $value) => $value === null || $value === '')
            ->sortKeys()
            ->map(fn (mixed $value) => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value)
            ->values()
            ->all();

        return sha1(implode('|', [(string) config('services.flitt.secret_key'), ...$values]));
    }

    public function hasValidSignature(array $data): bool
    {
        return isset($data['signature']) && is_string($data['signature']) && hash_equals($this->signature($data), $data['signature']);
    }

    protected function sign(array $params): array
    {
        $params = array_filter($params, fn (mixed $value) => $value !== null && $value !== '');

        return $params + ['signature' => $this->signature($params)];
    }

    protected function minorUnits(float $amount): int
    {
        return (int) round($amount * 100);
    }

    protected function url(string $path): string
    {
        return rtrim((string) config('services.flitt.api_url'), '/').'/'.$path;
    }
}
