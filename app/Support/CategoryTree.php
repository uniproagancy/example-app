<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Once;

class CategoryTree
{
    public const CACHE_KEY = 'categories.tree';

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        Once::flush();
    }

    public static function nodes(?string $locale = null): Collection
    {
        $locale ??= app()->getLocale();

        return once(function () use ($locale) {
            $raw = static::raw();
            $fallback = config('app.fallback_locale');
            $translations = collect($raw['translations'])->groupBy('category_id');

            $nodes = collect($raw['categories'])->mapWithKeys(function (array $category) use ($translations, $locale, $fallback) {
                $rows = $translations->get($category['id'], collect());
                $translation = $rows->firstWhere('locale', $locale) ?? $rows->firstWhere('locale', $fallback) ?? $rows->first() ?? [];

                return [$category['id'] => [
                    'id' => $category['id'],
                    'parent_id' => $category['parent_id'],
                    'code' => $category['code'],
                    'icon' => $category['icon'] ?: CategoryIcon::guess((string) $category['code'], ...$rows->pluck('title')->all()),
                    'show_on_main' => (bool) $category['show_on_main'],
                    'title' => $translation['title'] ?? (string) $category['code'],
                    'short' => ($translation['short_title'] ?? null) ?: ($translation['title'] ?? (string) $category['code']),
                    'slug' => $translation['slug'] ?? (string) $category['id'],
                    'description' => $translation['description'] ?? null,
                    'slugs' => $rows->pluck('slug', 'locale')->all(),
                ]];
            });

            $childrenOf = $nodes->groupBy(fn (array $node) => $node['parent_id'] ?? 0)->map->pluck('id');

            $collectSubtree = function (int $id) use (&$collectSubtree, $childrenOf): array {
                return array_merge([$id], ...$childrenOf->get($id, collect())->map(fn ($childId) => $collectSubtree($childId))->all());
            };

            return $nodes->map(function (array $node) use ($childrenOf, $collectSubtree, $nodes) {
                $subtree = $collectSubtree($node['id']);

                return [
                    ...$node,
                    'children' => $childrenOf->get($node['id'], collect())->values()->all(),
                    'subtree' => $subtree,
                    'subtree_codes' => collect($subtree)->map(fn ($id) => $nodes[$id]['code'])->filter()->values()->all(),
                ];
            });
        });
    }

    public static function roots(): Collection
    {
        return static::nodes()->whereNull('parent_id')->values();
    }

    public static function children(int $id): Collection
    {
        $nodes = static::nodes();

        return collect($nodes[$id]['children'] ?? [])->map(fn ($childId) => $nodes[$childId])->values();
    }

    public static function find(int $id): ?array
    {
        return static::nodes()->get($id);
    }

    public static function findByCode(string $code): ?array
    {
        return static::nodes()->firstWhere('code', $code);
    }

    public static function findBySlug(string $slug): ?array
    {
        return static::nodes()->firstWhere('slug', $slug);
    }

    public static function findBySlugInAnyLocale(string $slug): ?array
    {
        return static::nodes()->first(fn (array $node) => in_array($slug, $node['slugs'], true));
    }

    public static function ancestors(int $id): Collection
    {
        $nodes = static::nodes();
        $trail = collect();
        $current = $nodes->get($id);

        while ($current && $current['parent_id']) {
            $current = $nodes->get($current['parent_id']);

            if ($current) {
                $trail->prepend($current);
            }
        }

        return $trail;
    }

    public static function url(array $node): string
    {
        return route('category', ['slug' => $node['slug']]);
    }

    public static function label(string $key): string
    {
        $node = static::findByCode($key) ?? (ctype_digit($key) ? static::find((int) $key) : null);

        return $node['title'] ?? $key;
    }

    protected static function raw(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => [
            'categories' => DB::table('categories')
                ->where('is_active', true)
                ->orderBy('position')
                ->orderBy('id')
                ->get(['id', 'parent_id', 'code', 'icon', 'show_on_main'])
                ->map(fn ($row) => (array) $row)
                ->all(),
            'translations' => DB::table('category_translations')
                ->get(['category_id', 'locale', 'title', 'short_title', 'slug', 'description'])
                ->map(fn ($row) => (array) $row)
                ->all(),
        ]);
    }
}
