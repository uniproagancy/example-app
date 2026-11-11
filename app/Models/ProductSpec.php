<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['product_id', 'attribute_id', 'is_key', 'position'])]
class ProductSpec extends Model
{
    use HasTranslations;

    protected array $translatable = ['value'];

    protected function casts(): array
    {
        return [
            'is_key' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function kaTranslation(): HasOne
    {
        return $this->hasOne(ProductSpecTranslation::class)->where('locale', 'ka');
    }

    public function enTranslation(): HasOne
    {
        return $this->hasOne(ProductSpecTranslation::class)->where('locale', 'en');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
