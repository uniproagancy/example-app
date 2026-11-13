<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'driver', 'external_id', 'amount', 'months', 'status', 'request', 'response', 'redirect_url', 'checked_at', 'paid_at'])]
class PaymentTransaction extends Model
{
    public const PENDING = 'pending';

    public const SUCCESS = 'success';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [self::PENDING, self::SUCCESS, self::FAILED, self::CANCELLED];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'months' => 'integer',
            'request' => 'array',
            'response' => 'array',
            'checked_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }
}
