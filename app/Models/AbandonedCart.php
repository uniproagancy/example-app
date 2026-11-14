<?php

namespace App\Models;

use App\Support\Cart;
use App\Support\Locales;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

#[Fillable(['token', 'user_id', 'order_id', 'name', 'phone', 'email', 'items', 'lines', 'subtotal', 'locale', 'contacted_at', 'reminded_at'])]
class AbandonedCart extends Model
{
    use HasFactory;
    use MassPrunable;

    public const SESSION_KEY = 'abandoned_cart';

    public const IDLE_MINUTES = 30;

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'lines' => 'array',
            'subtotal' => 'decimal:2',
            'contacted_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function prunable(): Builder
    {
        return static::query()->where('updated_at', '<', now()->subDays(90));
    }

    public function scopeAbandoned(Builder $query): Builder
    {
        return $query->whereNull('order_id')->where('updated_at', '<=', now()->subMinutes(self::IDLE_MINUTES));
    }

    public static function remember(array $contact): ?self
    {
        $items = Cart::items();
        $lines = Cart::lines($items);

        if ($lines === [] || (blank($contact['phone'] ?? null) && blank($contact['email'] ?? null))) {
            return null;
        }

        $token = session(self::SESSION_KEY);

        if (! is_string($token) || ! Str::isUuid($token)) {
            $token = (string) Str::uuid();
            session([self::SESSION_KEY => $token]);
        }

        return static::query()->updateOrCreate(['token' => $token], [
            'user_id' => auth()->id(),
            'order_id' => null,
            'name' => filled($contact['name'] ?? null) ? mb_substr(trim($contact['name']), 0, 255) : null,
            'phone' => filled($contact['phone'] ?? null) ? mb_substr(trim($contact['phone']), 0, 32) : null,
            'email' => filled($contact['email'] ?? null) ? mb_strtolower(trim($contact['email'])) : null,
            'items' => $items,
            'lines' => collect($lines)->map(fn (array $line) => [
                'id' => $line['id'],
                'name' => $line['name'],
                'options' => $line['options'] ?? [],
                'price' => (float) $line['price'],
                'quantity' => (int) $line['quantity'],
            ])->all(),
            'subtotal' => (float) collect($lines)->sum('subtotal'),
            'locale' => app()->getLocale(),
        ]);
    }

    public static function recover(Order $order): void
    {
        $token = session()->pull(self::SESSION_KEY);

        if (is_string($token)) {
            static::query()->where('token', $token)->update(['order_id' => $order->id]);
        }
    }

    public function restoreUrl(int $days = 7): string
    {
        $locale = in_array($this->locale, Locales::codes(), true) ? $this->locale : Locales::default();

        return URL::temporarySignedRoute('cart.restore', now()->addDays($days), ['token' => $this->token, 'locale' => $locale]);
    }

    public function itemCount(): int
    {
        return (int) collect($this->lines)->sum('quantity');
    }
}
