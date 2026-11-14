<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Services\Orders\OrderNotifier;
use App\Support\Locales;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;

#[Fillable([
    'number', 'user_id', 'status', 'source', 'first_name', 'last_name', 'phone', 'email', 'shipping_method', 'city', 'address',
    'apartment', 'note', 'payment_method', 'payment_status', 'paid_at', 'installment_months', 'bank', 'term_months', 'promo_code',
    'subtotal', 'discount', 'shipping', 'total', 'locale',
])]
class Order extends Model
{
    use LogsActivity;
    use SoftDeletes;

    public const STATUSES = ['new', 'received', 'no_answer', 'call_later', 'completed', 'cancelled'];

    public const SOURCES = ['checkout', 'quick'];

    public const SHIPPING_METHODS = ['courier'];

    public const CITIES = ['tbilisi', 'batumi', 'kutaisi', 'rustavi', 'gori', 'zugdidi'];

    public const PAYMENT_PENDING = 'pending';

    public const PAYMENT_PROCESSING = 'processing';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_FAILED = 'failed';

    public const PAYMENT_REFUNDED = 'refunded';

    public const PAYMENT_STATUSES = [self::PAYMENT_PENDING, self::PAYMENT_PROCESSING, self::PAYMENT_PAID, self::PAYMENT_FAILED, self::PAYMENT_REFUNDED];

    protected static function booted(): void
    {
        static::deleted(function (Order $order): void {
            if (! $order->isForceDeleting()) {
                $order->log('deleted');
            }
        });

        static::restored(fn (Order $order) => $order->log('restored'));

        static::updated(function (Order $order): void {
            if ($order->wasChanged('payment_status') && $order->payment_status === self::PAYMENT_PAID) {
                app(OrderNotifier::class)->paid($order);
            }
        });
    }

    public function logFirstOpen(): void
    {
        if (auth()->check() && ! $this->events()->where('type', 'opened')->exists()) {
            $this->log('opened');
        }
    }

    protected function casts(): array
    {
        return [
            'term_months' => 'integer',
            'installment_months' => 'integer',
            'paid_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'shipping' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public static function generateNumber(): string
    {
        do {
            $number = 'EL-'.random_int(100000, 999999);
        } while (static::withTrashed()->where('number', $number)->exists());

        return $number;
    }

    public function isQuick(): bool
    {
        return $this->source === 'quick';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method', 'code');
    }

    public function pageUrl(): string
    {
        $locale = in_array($this->locale, Locales::codes(), true) ? $this->locale : Locales::default();

        return url($locale.'/order/'.$this->number);
    }

    public function invoiceNumber(): string
    {
        return 'INV-'.preg_replace('/^[A-Za-z]+-/', '', $this->number);
    }

    public function invoiceUrl(?string $locale = null): string
    {
        $locale ??= in_array($this->locale, Locales::codes(), true) ? $this->locale : Locales::default();

        return url($locale.'/order/'.$this->number.'/invoice');
    }

    public function payLink(int $days = 7, ?string $method = null): string
    {
        return URL::temporarySignedRoute('payment.link', now()->addDays($days), array_filter([
            'number' => $this->number,
            'method' => $method,
        ]));
    }

    public function rememberInSession(): void
    {
        if (! in_array($this->id, (array) session('placed_orders', []), true)) {
            session()->push('placed_orders', $this->id);
        }
    }

    public function mayBePaidByVisitor(): bool
    {
        if (auth()->check() && $this->user_id === auth()->id()) {
            return true;
        }

        return in_array($this->id, (array) session('placed_orders', []), true);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_PAID;
    }

    public function paymentLabel(): ?string
    {
        return PaymentMethod::labelFor($this->payment_method);
    }

    public function setPaymentStatus(string $status, ?string $note = null): bool
    {
        $from = $this->payment_status;

        if ($from === $status) {
            return false;
        }

        $this->update([
            'payment_status' => $status,
            'paid_at' => $status === self::PAYMENT_PAID ? ($this->paid_at ?? now()) : ($status === self::PAYMENT_REFUNDED ? $this->paid_at : null),
        ]);

        $this->log('payment', $note, $from, $status);

        return true;
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->latest()->latest('id');
    }

    public function isEditable(): bool
    {
        return ! in_array($this->status, ['completed', 'cancelled'], true);
    }

    public function log(string $type, ?string $note = null, ?string $from = null, ?string $to = null): OrderEvent
    {
        return $this->events()->create([
            'user_id' => auth()->id(),
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'note' => $note,
        ]);
    }

    public function totalsFor(array $lines, float $shipping): array
    {
        $subtotal = round((float) collect($lines)->sum(fn (array $line) => (float) $line['price'] * (int) $line['quantity']), 2);
        $promo = $this->promo_code ? PromoCode::query()->where('code', $this->promo_code)->first() : null;
        $discount = $promo ? $promo->discountFor($subtotal) : min((float) $this->discount, $subtotal);
        $shipping = round(max(0, $shipping), 2);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping' => $shipping,
            'total' => max(0, round($subtotal - $discount + $shipping, 2)),
        ];
    }
}
