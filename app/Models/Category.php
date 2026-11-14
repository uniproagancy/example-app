<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsActivity;
use App\Support\CategoryTree;
use App\Support\Slug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

#[Fillable(['parent_id', 'created_by_supplier_id', 'code', 'icon', 'google_category_id', 'in_feed', 'position', 'is_active', 'show_on_main'])]
class Category extends Model
{
    use HasTranslations;
    use LogsActivity;

    protected array $translatable = ['title', 'short_title', 'slug', 'description', 'name'];

    public const ICONS = ['mobile', 'computers', 'tv', 'gaming', 'photo', 'smart', 'small', 'kettle', 'acc', 'headphones', 'beauty', 'cleaning', 'scooter', 'car', 'tools', 'baby', 'furniture', 'security', 'kitchen', 'toys', 'newyear'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
            'show_on_main' => 'boolean',
            'google_category_id' => 'integer',
            'in_feed' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => CategoryTree::flush());
        static::deleted(fn () => CategoryTree::flush());
    }

    public static function createUnder(?int $parentId, string $title, ?string $englishTitle = null, array $attributes = []): self
    {
        return DB::transaction(function () use ($parentId, $title, $englishTitle, $attributes): self {
            $category = static::query()->create([
                'parent_id' => $parentId,
                'is_active' => true,
                'show_on_main' => false,
                'position' => (int) static::query()->where('parent_id', $parentId)->max('position') + 1,
                ...$attributes,
            ]);

            $english = filled($englishTitle) ? $englishTitle : $title;

            $category->saveTranslations([
                'ka' => ['title' => $title, 'slug' => Slug::make($title)],
                'en' => ['title' => $english, 'slug' => Slug::make($english)],
            ]);

            return $category;
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function filterAttributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'attribute_category')->withTimestamps();
    }

    public function ancestorIds(): array
    {
        $ids = [];
        $parentId = $this->parent_id;
        $guard = 0;

        while ($parentId && $guard++ < 10) {
            $ids[] = $parentId;
            $parentId = static::query()->whereKey($parentId)->value('parent_id');
        }

        return $ids;
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function kaTranslation(): HasOne
    {
        return $this->hasOne(CategoryTranslation::class)->where('locale', 'ka');
    }

    public function enTranslation(): HasOne
    {
        return $this->hasOne(CategoryTranslation::class)->where('locale', 'en');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    public function translation(?string $locale = null): ?CategoryTranslation
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

    public function scopeRoots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }
}
