<?php

namespace App\Filament\Support;

use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryOptions
{
    protected static ?array $memo = null;

    protected static ?array $aliasMemo = null;

    public static function flush(): void
    {
        static::$memo = null;
        static::$aliasMemo = null;
    }

    public static function aliases(): array
    {
        return static::$aliasMemo ??= Category::query()
            ->whereNotNull('code')
            ->pluck('code', 'id')
            ->map(fn (string $code) => str_replace(['-', '_'], ' ', $code))
            ->all();
    }

    public static function suggest(?string $name): array
    {
        return OptionSearch::suggest(static::paths(), $name, aliases: static::aliases());
    }

    public static function search(?string $search): array
    {
        return OptionSearch::search(static::paths(), $search, aliases: static::aliases());
    }

    public static function paths(?int $excludeSubtreeOf = null): array
    {
        if ($excludeSubtreeOf === null && static::$memo !== null) {
            return static::$memo;
        }

        $categories = Category::query()
            ->with('kaTranslation')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        $excluded = $excludeSubtreeOf ? static::subtreeIds($categories, $excludeSubtreeOf) : [];

        $paths = $categories
            ->reject(fn (Category $category) => in_array($category->id, $excluded, true))
            ->mapWithKeys(fn (Category $category) => [$category->id => static::path($categories, $category)])
            ->sort()
            ->all();

        if ($excludeSubtreeOf === null) {
            static::$memo = $paths;
        }

        return $paths;
    }

    protected static function path(Collection $categories, Category $category): string
    {
        $titles = [];
        $current = $category;
        $guard = 0;

        while ($current && $guard++ < 10) {
            array_unshift($titles, $current->kaTranslation?->title ?? '#'.$current->id);
            $current = $current->parent_id ? $categories->get($current->parent_id) : null;
        }

        return implode(' › ', $titles);
    }

    protected static function subtreeIds(Collection $categories, int $rootId): array
    {
        $ids = [$rootId];
        $queue = [$rootId];

        while ($queue) {
            $parentId = array_shift($queue);

            foreach ($categories->where('parent_id', $parentId) as $child) {
                $ids[] = $child->id;
                $queue[] = $child->id;
            }
        }

        return $ids;
    }
}
