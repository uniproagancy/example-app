<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Services\Payments\PaymentManager;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

#[Fillable(['code', 'driver', 'installment_type', 'logo', 'is_active', 'is_online', 'on_quick_order', 'min_total', 'max_total', 'sort_order'])]
class PaymentMethod extends Model
{
    use HasTranslations;

    protected array $translatable = ['name', 'note'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_online' => 'boolean',
            'on_quick_order' => 'boolean',
            'min_total' => 'decimal:2',
            'max_total' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function kaTranslation(): HasOne
    {
        return $this->hasOne(PaymentMethodTranslation::class)->where('locale', 'ka');
    }

    public function enTranslation(): HasOne
    {
        return $this->hasOne(PaymentMethodTranslation::class)->where('locale', 'en');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public static function forCheckout(float $total, bool $quick = false): Collection
    {
        return static::query()
            ->active()
            ->withTranslation()
            ->when($quick, fn (Builder $query) => $query->where('on_quick_order', true))
            ->get()
            ->filter(fn (self $method) => $method->fitsTotal($total) && $method->canRun())
            ->values();
    }

    public static function labelFor(?string $code): ?string
    {
        if (! $code) {
            return null;
        }

        return once(fn () => static::query()->withTranslation()->get()->keyBy('code'))->get($code)?->name ?? $code;
    }

    public function fitsTotal(float $total): bool
    {
        return ($this->min_total === null || $total >= (float) $this->min_total)
            && ($this->max_total === null || $total <= (float) $this->max_total);
    }

    public function canRun(): bool
    {
        return ! $this->is_online || $this->isPayableOnline();
    }

    public function isPayableOnline(): bool
    {
        return $this->is_online && app(PaymentManager::class)->isReady($this->driver);
    }

    public function logoUrl(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        return match (true) {
            str_starts_with($this->logo, 'http') => $this->logo,
            str_starts_with($this->logo, '/') => url($this->logo),
            default => Storage::disk('public')->url($this->logo),
        };
    }
}
