<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Support\Catalog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

#[Fillable(['category_id', 'brand_id', 'sku', 'supplier_sku', 'price', 'old_price', 'cost_price', 'price_lock', 'taxonomy_lock', 'stock', 'weight', 'variant_group', 'is_active', 'is_new', 'is_preorder', 'release_date', 'position', 'synced_at'])]
class Product extends Model
{
    use HasTranslations;

    protected array $translatable = ['name', 'slug', 'subtitle', 'description'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'old_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'price_lock' => 'boolean',
            'taxonomy_lock' => 'boolean',
            'weight' => 'integer',
            'is_preorder' => 'boolean',
            'release_date' => 'date',
            'synced_at' => 'datetime',
            'stock' => 'integer',
            'is_active' => 'boolean',
            'is_new' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Catalog::flush());
        static::deleted(fn () => Catalog::flush());
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(ProductOffer::class);
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'attribute_value_product', 'product_id', 'attribute_value_id');
    }

    public function scopeReadyToPublish(Builder $query): Builder
    {
        return $query->whereNotNull('category_id')
            ->where('price', '>', 0)
            ->whereHas('translations', fn (Builder $translations) => $translations->where('locale', 'ka')->where('name', '!=', ''));
    }

    public function siteUrl(): ?string
    {
        $slug = $this->kaTranslation?->slug;

        return $slug ? url('ka/product/'.$slug) : null;
    }

    public function coverImageUrl(): ?string
    {
        $path = $this->coverImage?->path;

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public function coverImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->ofMany('position', 'min');
    }

    public function kaTranslation(): HasOne
    {
        return $this->hasOne(ProductTranslation::class)->where('locale', 'ka');
    }

    public function enTranslation(): HasOne
    {
        return $this->hasOne(ProductTranslation::class)->where('locale', 'en');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ProductTranslation::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    public function specs(): HasMany
    {
        return $this->hasMany(ProductSpec::class)->orderBy('position');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('position');
    }

    public function translation(?string $locale = null): ?ProductTranslation
    {
        $locale ??= app()->getLocale();

        return $this->translations->firstWhere('locale', $locale)
            ?? $this->translations->firstWhere('locale', config('app.fallback_locale'))
            ?? $this->translations->first();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
