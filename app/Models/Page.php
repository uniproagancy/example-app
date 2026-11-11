<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

#[Fillable(['slug', 'is_active', 'in_footer', 'position'])]
class Page extends Model
{
    use HasTranslations;

    public const CACHE_KEY = 'pages:menu';

    protected array $translatable = ['title', 'intro', 'body', 'meta_description'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'in_footer' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public function kaTranslation(): HasOne
    {
        return $this->hasOne(PageTranslation::class)->where('locale', 'ka');
    }

    public function enTranslation(): HasOne
    {
        return $this->hasOne(PageTranslation::class)->where('locale', 'en');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('position')->orderBy('id');
    }

    public static function menu(): Collection
    {
        try {
            $pages = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()
                ->active()
                ->with('translations')
                ->get()
                ->map(fn (self $page) => [
                    'slug' => $page->slug,
                    'in_footer' => $page->in_footer,
                    'titles' => $page->translations->pluck('title', 'locale')->all(),
                ])
                ->all());
        } catch (QueryException) {
            return collect();
        }

        return collect($pages)->map(fn (array $page) => [
            ...$page,
            'title' => $page['titles'][app()->getLocale()] ?? $page['titles']['ka'] ?? reset($page['titles']) ?: $page['slug'],
            'url' => route('page', ['slug' => $page['slug']]),
        ]);
    }

    public static function link(string $slug): ?string
    {
        return static::menu()->firstWhere('slug', $slug)['url'] ?? null;
    }
}
