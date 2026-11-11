<?php

namespace App\Models;

use App\Support\Catalog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'type', 'label', 'hex', 'price', 'old_price', 'is_default', 'position'])]
class ProductOption extends Model
{
    public const TYPES = ['color', 'storage'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'label' => 'array',
            'price' => 'decimal:2',
            'old_price' => 'decimal:2',
            'is_default' => 'boolean',
            'position' => 'integer',
        ];
    }

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
