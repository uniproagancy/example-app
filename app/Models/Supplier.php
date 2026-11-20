<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'driver', 'is_active', 'priority', 'config', 'markup', 'last_run_at'])]
class Supplier extends Model
{
    use SoftDeletes;

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

    /**
     * @return array<int, array{up_to: ?float, add: float, percent: float}>
     */
    public function markupTiers(): array
    {
        return collect((array) ($this->config['markup'] ?? []))
            ->filter(fn ($tier) => is_array($tier))
            ->map(fn (array $tier) => [
                'up_to' => isset($tier['up_to']) && is_numeric($tier['up_to']) ? (float) $tier['up_to'] : null,
                'add' => is_numeric($tier['add'] ?? null) ? (float) $tier['add'] : 0.0,
                'percent' => is_numeric($tier['percent'] ?? null) ? (float) $tier['percent'] : 0.0,
            ])
            ->sortBy(fn (array $tier) => $tier['up_to'] ?? PHP_FLOAT_MAX)
            ->values()
            ->all();
    }

    public function sellingPrice(float $cost): float
    {
        if ($cost <= 0) {
            return $cost;
        }

        foreach ($this->markupTiers() as $tier) {
            if ($tier['up_to'] === null || $cost <= $tier['up_to']) {
                return round($cost + $cost * $tier['percent'] / 100 + $tier['add'], 2);
            }
        }

        return $cost;
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
}
