<?php

namespace App\Models;

use App\Models\Concerns\GeneratesUniqueSlug;
use App\Support\CategoryTree;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['category_id', 'locale', 'title', 'short_title', 'slug', 'description'])]
class CategoryTranslation extends Model
{
    use GeneratesUniqueSlug;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::saved(fn () => CategoryTree::flush());
        static::deleted(fn () => CategoryTree::flush());
    }

    protected function name(): Attribute
    {
        return Attribute::get(fn () => $this->title);
    }

    protected function slugSource(): string
    {
        return (string) $this->title;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
