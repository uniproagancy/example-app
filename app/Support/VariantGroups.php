<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VariantGroups
{
    public static function siblingIds(Product $product): array
    {
        if (blank($product->variant_group)) {
            return [];
        }

        return Product::query()
            ->where('variant_group', $product->variant_group)
            ->whereKeyNot($product->getKey())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public static function link(Product $product, array $siblingIds): void
    {
        $ids = collect($siblingIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0 && $id !== (int) $product->getKey())
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($product, $ids): void {
            $current = $product->variant_group;

            if ($ids === []) {
                if ($current) {
                    $product->update(['variant_group' => null]);
                    static::dissolveIfAlone($current);
                }

                return;
            }

            $group = $current
                ?: Product::query()->whereKey($ids)->whereNotNull('variant_group')->value('variant_group')
                ?: 'g-'.Str::lower(Str::random(12));

            Product::query()
                ->where('variant_group', $group)
                ->whereKeyNot($product->getKey())
                ->whereNotIn('id', $ids)
                ->update(['variant_group' => null]);

            Product::query()->whereKey([...$ids, $product->getKey()])->update(['variant_group' => $group]);
        });

        $product->refresh();
        Catalog::flush();
    }

    protected static function dissolveIfAlone(string $group): void
    {
        if (Product::query()->where('variant_group', $group)->count() === 1) {
            Product::query()->where('variant_group', $group)->update(['variant_group' => null]);
        }
    }
}
