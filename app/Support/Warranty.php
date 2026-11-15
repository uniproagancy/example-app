<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class Warranty
{
    public const DEFAULT_MONTHS = 12;

    public const SETTING = 'warranty_months';

    public const SPEC_NAMES = ['გარანტია', 'warranty', 'garantia', 'გარანტიის ვადა'];

    public static function defaultMonths(): int
    {
        $months = (int) Setting::get(self::SETTING);

        return $months > 0 ? $months : self::DEFAULT_MONTHS;
    }

    public static function monthsFor(?int $productId): int
    {
        if ($productId === null) {
            return static::defaultMonths();
        }

        $product = DB::table('products')->where('id', $productId)->first(['category_id', 'warranty_months']);

        if ($product?->warranty_months) {
            return (int) $product->warranty_months;
        }

        return static::monthsForCategory($product?->category_id ? (int) $product->category_id : null);
    }

    public static function monthsForCategory(?int $categoryId): int
    {
        $categories = static::categories();
        $seen = [];

        while ($categoryId !== null && isset($categories[$categoryId]) && ! isset($seen[$categoryId])) {
            $seen[$categoryId] = true;
            [$parentId, $months] = $categories[$categoryId];

            if ($months) {
                return $months;
            }

            $categoryId = $parentId;
        }

        return static::defaultMonths();
    }

    public static function inheritedForCategory(?int $categoryId): int
    {
        $parentId = $categoryId !== null ? (static::categories()[$categoryId][0] ?? null) : null;

        return static::monthsForCategory($parentId);
    }

    public static function label(int $months, ?string $locale = null): string
    {
        if ($months >= 12 && $months % 12 === 0) {
            return trans_choice('warranty.years', intdiv($months, 12), ['count' => intdiv($months, 12)], $locale);
        }

        return trans_choice('warranty.months', $months, ['count' => $months], $locale);
    }

    public static function isWarrantySpec(?string $name): bool
    {
        return in_array(mb_strtolower(trim((string) $name)), self::SPEC_NAMES, true);
    }

    protected static function categories(): array
    {
        return once(function (): array {
            return DB::table('categories')
                ->whereNull('deleted_at')
                ->get(['id', 'parent_id', 'warranty_months'])
                ->mapWithKeys(fn (object $row) => [(int) $row->id => [$row->parent_id ? (int) $row->parent_id : null, (int) $row->warranty_months]])
                ->all();
        });
    }
}
