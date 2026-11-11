<?php

namespace App\Models;

use App\Support\HomeContent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'title', 'kicker', 'position', 'is_active'])]
class HomeBlock extends Model
{
    public const KEYS = ['perks', 'sale', 'deal', 'categories', 'promos', 'brands'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'kicker' => 'array',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => HomeContent::flush());
        static::deleted(fn () => HomeContent::flush());
    }
}
