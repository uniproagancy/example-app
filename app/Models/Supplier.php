<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'driver', 'is_active', 'priority', 'config', 'markup', 'last_run_at'])]
class Supplier extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'priority' => 'integer',
            'config' => 'array',
            'markup' => 'array',
            'last_run_at' => 'datetime',
        ];
    }

    public function offers(): HasMany
    {
        return $this->hasMany(ProductOffer::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(SupplierStock::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('priority');
    }

    public function markup(float $cost): float
    {
        foreach ($this->markup ?? [] as $tier) {
            if (blank($tier['up_to'] ?? null) || $cost <= (float) $tier['up_to']) {
                return filled($tier['percent'] ?? null)
                    ? self::toLari($cost * (1 + (float) $tier['percent'] / 100))
                    : self::toLari($cost + (float) ($tier['add'] ?? 0));
            }
        }

        return self::toLari($cost);
    }

    public static function toLari(float $amount): float
    {
        return round($amount, 0);
    }
}
