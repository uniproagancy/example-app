<?php

namespace App\Models;

use App\Models\Concerns\GeneratesUniqueSlug;
use App\Models\Concerns\LogsActivity;
use App\Support\CategoryTree;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['category_id', 'locale', 'title', 'short_title', 'slug', 'description'])]
class CategoryTranslation extends Model
{
    use GeneratesUniqueSlug;
    use LogsActivity;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updated(function (self $translation): void {
            if ($translation->wasChanged('slug') && filled($translation->getOriginal('slug'))) {
                Redirect::record(
                    '/'.$translation->locale.'/categories/'.$translation->getOriginal('slug'),
                    '/'.$translation->locale.'/categories/'.$translation->slug,
                );
            }
        });

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
