<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Support\Catalog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'price', 'text', 'position', 'is_active', 'starts_at', 'ends_at'])]
class Deal extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'text' => 'array',
            'position' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
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
