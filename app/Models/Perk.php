<?php

namespace App\Models;

use App\Support\HomeContent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['icon', 'title', 'text', 'position', 'is_active'])]
class Perk extends Model
{
    public const ICONS = ['delivery', 'warranty', 'installments', 'returns', 'support', 'store'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'text' => 'array',
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
