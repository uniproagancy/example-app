<?php

namespace App\Models;

use App\Support\Warranty;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['order_id', 'product_id', 'name', 'options', 'unit_price', 'quantity', 'total', 'warranty_months', 'serial_number', 'serial_photo', 'supplier_ordered_at', 'supplier_ordered_by', 'pickup_warehouse', 'pickup_address', 'pickup_note', 'picked_up_at', 'picked_up_by'])]
class OrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'total' => 'decimal:2',
            'warranty_months' => 'integer',
            'supplier_ordered_at' => 'datetime',
            'picked_up_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (OrderItem $item): void {
            $item->warranty_months ??= Warranty::monthsFor($item->product_id);
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supplier_ordered_by');
    }

    public function pickedUpBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picked_up_by');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warrantyMonths(): int
    {
        return $this->warranty_months ?: Warranty::defaultMonths();
    }

    public function warrantyLabel(?string $locale = null): string
    {
        return Warranty::label($this->warrantyMonths(), $locale);
    }

    public function warrantyEndsAt(): ?Carbon
    {
        $startsAt = $this->order?->warranty_starts_at;

        return $startsAt?->copy()->addMonthsNoOverflow($this->warrantyMonths())->subDay();
    }

    public function warrantyState(): string
    {
        if (! $this->order?->warrantyIssued() || ($endsAt = $this->warrantyEndsAt()) === null) {
            return 'pending';
        }

        return $endsAt->endOfDay()->isFuture() ? 'active' : 'expired';
    }
}
