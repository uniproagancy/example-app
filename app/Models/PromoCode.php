<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'percent', 'min_subtotal', 'is_active', 'starts_at', 'ends_at'])]
class PromoCode extends Model
{
    protected function casts(): array
    {
        return [
            'percent' => 'integer',
            'min_subtotal' => 'decimal:2',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public static function findUsable(string $code, float $subtotal): ?self
    {
        $promo = static::query()
            ->where('code', mb_strtoupper(trim($code)))
            ->where('is_active', true)
            ->first();

        if (! $promo || ($promo->starts_at && $promo->starts_at->isFuture()) || ($promo->ends_at && $promo->ends_at->isPast())) {
            return null;
        }

        if ($promo->min_subtotal !== null && $subtotal < (float) $promo->min_subtotal) {
            return null;
        }

        return $promo;
    }

    public function discountFor(float $subtotal): float
    {
        return round($subtotal * $this->percent / 100);
    }
}
