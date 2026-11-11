<?php

namespace App\Models;

use App\Support\Catalog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'path', 'hash', 'position'])]
class ProductImage extends Model
{
    protected static function booted(): void
    {
        static::saved(fn () => Catalog::flush());
        static::deleted(fn () => Catalog::flush());
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
