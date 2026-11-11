<?php

namespace App\Services\Import;

use App\Models\Attribute;
use App\Models\Category;

class ImportRules
{
    protected static array $memo = [];

    public static function categoryIds(string $rule): array
    {
        return static::$memo["categories:$rule"] ??= static::withDescendants(
            Category::query()->whereIn('code', (array) config("import.rules.$rule.categories", []))->pluck('id')->all(),
        );
    }

    public static function family(?int $categoryId): ?string
    {
        if ($categoryId === null) {
            return null;
        }

        foreach (array_keys((array) config('import.families', [])) as $family) {
            if (in_array($categoryId, static::familyCategoryIds($family), true)) {
                return $family;
            }
        }

        return null;
    }

    public static function familyCategoryIds(string $family): array
    {
        return static::$memo["family:$family"] ??= static::withDescendants(
            Category::query()->whereIn('code', (array) config("import.families.$family.categories", []))->pluck('id')->all(),
        );
    }

    public static function attributeId(?string $code): ?int
    {
        if (blank($code)) {
            return null;
        }

        if (! array_key_exists("attribute:$code", static::$memo)) {
            static::$memo["attribute:$code"] = Attribute::query()->where('code', $code)->value('id');
        }

        return static::$memo["attribute:$code"] !== null ? (int) static::$memo["attribute:$code"] : null;
    }

    public static function flush(): void
    {
        static::$memo = [];
    }

    protected static function withDescendants(array $ids): array
    {
        $all = array_map('intval', $ids);
        $frontier = $all;

        while ($frontier) {
            $frontier = array_values(array_diff(
                Category::query()->whereIn('parent_id', $frontier)->pluck('id')->map(fn ($id) => (int) $id)->all(),
                $all,
            ));
            $all = [...$all, ...$frontier];
        }

        return $all;
    }
}
