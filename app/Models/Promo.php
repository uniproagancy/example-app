<?php

namespace App\Models;

use App\Support\HomeContent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['badge', 'title', 'text', 'note', 'category_id', 'url', 'theme', 'icon', 'position', 'is_active', 'starts_at', 'ends_at'])]
class Promo extends Model
{
    public const THEMES = ['navy', 'tint', 'field'];

    protected function casts(): array
    {
        return [
            'badge' => 'array',
            'title' => 'array',
            'text' => 'array',
            'note' => 'array',
            'position' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => HomeContent::flush());
        static::deleted(fn () => HomeContent::flush());
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
