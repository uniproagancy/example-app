<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Support\Catalog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['code', 'type', 'unit', 'is_filterable', 'is_variant', 'sort_order'])]
class Attribute extends Model
{
    use HasTranslations;

    public const TYPE_SELECT = 'select';

    public const TYPE_COLOR = 'color';

    public const TYPE_TEXT = 'text';

    public const TYPES = [self::TYPE_SELECT, self::TYPE_COLOR, self::TYPE_TEXT];

    protected array $translatable = ['name'];

    protected function casts(): array
    {
        return [
            'is_filterable' => 'boolean',
            'is_variant' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Catalog::flush());
        static::deleted(fn () => Catalog::flush());
    }

    public function kaTranslation(): HasOne
    {
        return $this->hasOne(AttributeTranslation::class)->where('locale', 'ka');
    }

    public function enTranslation(): HasOne
    {
        return $this->hasOne(AttributeTranslation::class)->where('locale', 'en');
    }

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('sort_order')->orderBy('id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withTimestamps();
    }

    public function specs(): HasMany
    {
        return $this->hasMany(ProductSpec::class);
    }

    public function scopeFilterable(Builder $query): Builder
    {
        return $query->where('is_filterable', true)->orderBy('sort_order');
    }

    public function hasValues(): bool
    {
        return $this->type !== self::TYPE_TEXT;
    }
}
