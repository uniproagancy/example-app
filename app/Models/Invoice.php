<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;

#[Fillable(['issued_on', 'buyer_name', 'buyer_company', 'buyer_code', 'phone', 'email', 'address', 'lines', 'discount', 'shipping', 'note', 'paid_at', 'user_id'])]
class Invoice extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'lines' => 'array',
            'discount' => 'decimal:2',
            'shipping' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function invoiceNumber(): string
    {
        return 'INV-'.($this->created_at ?? now())->format('y').'-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function items(): Collection
    {
        return collect($this->lines ?? [])
            ->filter(fn ($line) => is_array($line) && trim((string) ($line['name'] ?? '')) !== '')
            ->map(function (array $line): Fluent {
                $quantity = max(1, (int) ($line['quantity'] ?? 1));
                $price = round((float) ($line['unit_price'] ?? 0), 2);

                return new Fluent([
                    'name' => trim($line['name']),
                    'options' => null,
                    'quantity' => $quantity,
                    'unit_price' => $price,
                    'total' => round($price * $quantity, 2),
                ]);
            })
            ->values();
    }

    public function subtotal(): float
    {
        return round((float) $this->items()->sum('total'), 2);
    }

    public function total(): float
    {
        return max(0, round($this->subtotal() - (float) $this->discount + (float) $this->shipping, 2));
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }
}
