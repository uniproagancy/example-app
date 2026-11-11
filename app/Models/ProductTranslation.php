<?php

namespace App\Models;

use App\Models\Concerns\GeneratesUniqueSlug;
use App\Support\Catalog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'locale', 'name', 'slug', 'subtitle', 'description'])]
class ProductTranslation extends Model
{
    use GeneratesUniqueSlug;

    public $timestamps = false;

    protected static function booted(): void
    {
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
