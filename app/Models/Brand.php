<?php

namespace App\Models;

use App\Support\Catalog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'position', 'is_active', 'show_on_home', 'home_position'])]
class Brand extends Model
{
    public const HOME_LIMIT = 8;

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
            'show_on_home' => 'boolean',
            'home_position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Brand $brand): void {
            if (blank($brand->slug)) {
                $brand->slug = $brand->uniqueSlug(Str::slug($brand->name) ?: Str::lower(Str::random(8)));
            }
        });
        static::saved(fn () => Catalog::flush());
        static::deleted(fn () => Catalog::flush());
    }

    protected function uniqueSlug(string $base): string
    {
        $slug = $base;
        $suffix = 2;

        while (static::query()->where('slug', $slug)->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
