<?php

namespace App\Models;

use App\Support\CategoryTree;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

#[Fillable(['title', 'text', 'button_label', 'category_id', 'url', 'image', 'theme', 'icon', 'position', 'is_active', 'starts_at', 'ends_at'])]
class Slide extends Model
{
    public const CACHE_KEY = 'slides.all';

    public const THEMES = ['blue', 'red', 'indigo'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'text' => 'array',
            'button_label' => 'array',
            'position' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeVisible(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public static function forHero(): Collection
    {
        $rows = Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes(10),
            fn () => static::query()->visible()->orderBy('position')->orderBy('id')->toBase()->get()->map(fn ($row) => (array) $row)->all(),
        );

        return static::hydrate($rows)
            ->filter(fn (Slide $slide) => (! $slide->starts_at || $slide->starts_at->isPast()) && (! $slide->ends_at || $slide->ends_at->isFuture()))
            ->values()
            ->map(fn (Slide $slide) => $slide->present());
    }

    public function present(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->localized($this->title),
            'text' => $this->localized($this->text),
            'buttonLabel' => $this->localized($this->button_label),
            'href' => $this->href(),
            'image' => $this->image ? Storage::disk('public')->url($this->image) : null,
            'theme' => in_array($this->theme, self::THEMES, true) ? $this->theme : 'blue',
            'icon' => $this->icon,
        ];
    }

    public function href(): ?string
    {
        if ($this->category_id && ($node = CategoryTree::find($this->category_id))) {
            return CategoryTree::url($node);
        }

        if ($this->url && str_starts_with($this->url, '/')) {
            return LaravelLocalization::localizeUrl($this->url);
        }

        return $this->url ?: null;
    }

    protected function localized(?array $values): ?string
    {
        if (! $values) {
            return null;
        }

        return $values[app()->getLocale()] ?? $values[config('app.fallback_locale')] ?? (string) reset($values);
    }
}
