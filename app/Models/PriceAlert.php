<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'user_id', 'contact', 'channel', 'price', 'notified_price', 'locale', 'notified_at'])]
class PriceAlert extends Model
{
    use HasFactory;
    use MassPrunable;

    public const CHANNELS = ['mail', 'sms'];

    public const MAX_PER_CONTACT = 30;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'notified_price' => 'decimal:2',
            'notified_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prunable(): Builder
    {
        return static::query()->where(fn (Builder $query) => $query
            ->where('notified_at', '<', now()->subDays(30))
            ->orWhere('created_at', '<', now()->subYear()));
    }

    public function scopeWaiting(Builder $query): Builder
    {
        return $query->whereNull('notified_at');
    }
}
