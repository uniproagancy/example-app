<?php

namespace App\Models;

use App\Models\Concerns\GeneratesUniqueSlug;
use App\Models\Concerns\LogsActivity;
use App\Support\Catalog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'locale', 'name', 'slug', 'subtitle', 'description'])]
class ProductTranslation extends Model
{
    use GeneratesUniqueSlug;
    use LogsActivity;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updated(function (self $translation): void {
            if ($translation->wasChanged('slug') && filled($translation->getOriginal('slug'))) {
                Redirect::record(
                    '/'.$translation->locale.'/product/'.$translation->getOriginal('slug'),
                    '/'.$translation->locale.'/product/'.$translation->slug,
                );
            }
        });

        static::saved(fn () => Catalog::flush());
        static::deleted(fn () => Catalog::flush());
    }

    protected function slugSource(): string
    {
        return (string) $this->name;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
