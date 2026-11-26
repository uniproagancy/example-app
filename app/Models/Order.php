<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Services\Orders\OrderNotifier;
use App\Support\Locales;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;

#[Fillable([
    'number', 'user_id', 'status', 'source', 'first_name', 'last_name', 'phone', 'email', 'shipping_method', 'city', 'address', 'is_region',
    'apartment', 'note', 'payment_method', 'payment_status', 'paid_at', 'completed_at', 'returned_at', 'courier_id', 'warranty_starts_at', 'warranty_issued_at', 'installment_months', 'bank', 'term_months', 'promo_code',
    'subtotal', 'discount', 'shipping', 'total', 'locale',
])]
class Order extends Model
{
    use LogsActivity;
    use SoftDeletes;

    public const STATUSES = ['new', 'received', 'confirmed', 'cancelled', 'no_answer', 'call_later', 'completed', 'returned'];

    public const LOST_STATUSES = ['cancelled', 'returned'];

    public const NOTIFIABLE_STATUSES = ['confirmed', 'completed', 'cancelled'];

    public const SOURCES = ['checkout', 'quick', 'admin'];

    public const SHIPPING_METHODS = ['courier'];

    public const PAYMENT_PENDING = 'pending';

    public const PAYMENT_PROCESSING = 'processing';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_FAILED = 'failed';

    public const PAYMENT_REFUNDED = 'refunded';

    public const PAYMENT_STATUSES = [self::PAYMENT_PENDING, self::PAYMENT_PROCESSING, self::PAYMENT_PAID, self::PAYMENT_FAILED, self::PAYMENT_REFUNDED];

    protected static function booted(): void
    {
        static::saving(function (Order $order): void {
            if ($order->isDirty('status') && $order->status === 'completed' && $order->completed_at === null) {
                $order->completed_at = now();
            }
        });

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

            if ($order->wasChanged('status')) {
                app(OrderNotifier::class)->statusChanged($order);
            }
        });
    }

    public function logFirstOpen(): void
    {
        if (auth()->check() && ! $this->events()->where('type', 'opened')->exists()) {
            $this->log('opened');
        }
    }

    public function placeLabel(): ?string
    {
        if ($this->is_region) {
            return __('checkout.region');
        }

        return $this->city ? __("checkout.cities.{$this->city}") : null;
    }

    protected function casts(): array
    {
        return [
            'is_region' => 'boolean',
            'term_months' => 'integer',
            'installment_months' => 'integer',
            'paid_at' => 'datetime',
            'completed_at' => 'datetime',
            'returned_at' => 'datetime',
            'warranty_starts_at' => 'date',
            'warranty_issued_at' => 'datetime',
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

    public function awaitsBankTransfer(): bool
    {
        return $this->payment_method === PaymentMethod::TRANSFER && ! $this->isPaid() && ! $this->isLost();
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

    public function warrantyLink(): string
    {
        return URL::signedRoute('warranty.link', ['number' => $this->number]);
    }

    public function warrantyUrl(?string $locale = null): string
    {
        $locale ??= in_array($this->locale, Locales::codes(), true) ? $this->locale : Locales::default();

        return url($locale.'/warranty/'.$this->number);
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

    public function rememberWarrantyAccess(): void
    {
        if (! in_array($this->id, (array) session('warranty_orders', []), true)) {
            session()->push('warranty_orders', $this->id);
        }
    }

    public function warrantyIssued(): bool
    {
        return $this->warranty_issued_at !== null && ! $this->isLost();
    }

    public function warrantyVisibleToVisitor(): bool
    {
        if (auth()->user()?->canAccessPanel(Filament::getPanel('admin'))) {
            return true;
        }

        return $this->warrantyIssued()
            && ($this->mayBePaidByVisitor() || in_array($this->id, (array) session('warranty_orders', []), true));
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
        return ! in_array($this->status, ['completed', ...self::LOST_STATUSES], true);
    }

    public function isLost(): bool
    {
        return in_array($this->status, self::LOST_STATUSES, true);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
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
