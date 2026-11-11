<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Support\Catalog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['attribute_id', 'code', 'color_hex', 'sort_order'])]
class AttributeValue extends Model
{
    use HasTranslations;

    protected array $translatable = ['label'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Catalog::flush());
        static::deleted(fn () => Catalog::flush());
    }

    public function kaTranslation(): HasOne
    {
        return $this->hasOne(AttributeValueTranslation::class)->where('locale', 'ka');
    }

    public function enTranslation(): HasOne
    {
        return $this->hasOne(AttributeValueTranslation::class)->where('locale', 'en');
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'attribute_value_product', 'attribute_value_id', 'product_id');
    }
}
