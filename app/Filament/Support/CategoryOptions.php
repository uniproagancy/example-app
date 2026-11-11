<?php

namespace App\Filament\Support;

use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryOptions
{
    protected static ?array $memo = null;

    protected static ?array $englishMemo = null;

    public static function flush(): void
    {
        static::$memo = null;
        static::$englishMemo = null;
    }

    public static function englishTitles(): array
    {
        return static::$englishMemo ??= Category::query()
            ->with('enTranslation')
            ->get()
            ->mapWithKeys(fn (Category $category) => [$category->id => (string) $category->enTranslation?->title])
            ->all();
    }

    public static function suggest(?string $name): array
    {
        return OptionSearch::suggest(static::paths(), $name, aliases: static::englishTitles());
    }

    public static function search(?string $search): array
    {
        return OptionSearch::search(static::paths(), $search, aliases: static::englishTitles());
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
