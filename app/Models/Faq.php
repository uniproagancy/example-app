<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['icon', 'question', 'answer', 'position', 'is_active'])]
class Faq extends Model
{
    public const ICONS = ['delivery', 'warranty', 'installments', 'returns', 'payment', 'help'];

    protected function casts(): array
    {
        return [
            'question' => 'array',
            'answer' => 'array',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeVisible(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('position')->orderBy('id');
    }

    public function localized(string $field): string
    {
        $values = $this->{$field} ?? [];

        return $values[app()->getLocale()] ?? $values[config('app.fallback_locale')] ?? (string) reset($values);
    }
}
