<?php

namespace App\Models;

use App\Support\Redact;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Fillable([
    'driver', 'method', 'path', 'query', 'headers', 'body', 'payload', 'ip', 'user_agent',
    'payment_transaction_id', 'order_id', 'outcome', 'confirmed', 'status_before', 'status_after',
    'error', 'duration_ms',
])]
class PaymentCallback extends Model
{
    public const UNKNOWN_DRIVER = 'unknown_driver';

    public const UNMATCHED = 'unmatched';

    public const PAID = 'paid';

    public const NOT_PAID = 'not_paid';

    public const FAILED = 'failed';

    protected const MAX_BODY = 65535;

    protected const SECRET_HEADERS = ['authorization', 'cookie', 'proxy-authorization', 'x-api-key', 'php-auth-pw'];

    protected function casts(): array
    {
        return [
            'query' => 'array',
            'headers' => 'array',
            'payload' => 'array',
            'confirmed' => 'boolean',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public static function receive(Request $request, string $driver): ?self
    {
        try {
            $headers = [];

            foreach ($request->headers->all() as $name => $values) {
                $headers[$name] = in_array(strtolower($name), self::SECRET_HEADERS, true)
                    ? '[redacted]'
                    : implode(', ', $values);
            }

            return self::query()->create([
                'driver' => mb_substr($driver, 0, 40),
                'method' => $request->method(),
                'path' => mb_substr($request->path(), 0, 255),
                'query' => Redact::fields($request->query->all()) ?: null,
                'headers' => $headers,
                'body' => mb_substr(Redact::json((string) $request->getContent()), 0, self::MAX_BODY) ?: null,
                'payload' => Redact::fields($request->all()) ?: null,
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
            ]);
        } catch (Throwable $e) {
            Log::channel('payments')->error('callback could not be recorded', [
                'driver' => $driver,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function finish(array $what): void
    {
        try {
            $this->update($what);
        } catch (Throwable $e) {
            Log::channel('payments')->error('callback outcome could not be recorded', [
                'callback' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
