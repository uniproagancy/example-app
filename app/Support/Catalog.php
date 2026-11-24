<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\Campaign;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Once;

class Catalog
{
    public const CACHE_KEY = 'catalog.products';

    public const FREE_SHIPPING_FROM = 100;

    public const SECTION_CHIPS = 4;

    public const SECTION_PRODUCTS = 12;

    public const SECTION_CHIP_PRODUCTS = 6;

    public const SHIPPING_PRICE = 5;

    public const INSTALLMENT_MONTHS = 24;

    public const NEW_FOR_DAYS = 2;

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        Once::flush();
    }

    public static function products(?string $locale = null): Collection
    {
        return static::productsFor($locale ?? app()->getLocale());
    }

    public static function description(int $id, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        return once(fn () => DB::table('product_translations')
            ->where('product_id', $id)
            ->whereIn('locale', array_unique([$locale, (string) config('app.fallback_locale')]))
            ->orderByRaw('locale = ? desc', [$locale])
            ->value('description'));
    }

    public static function images(int $id): array
    {
        return once(fn () => DB::table('product_images')
            ->where('product_id', $id)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('path')
            ->map(fn (string $path) => Storage::disk('public')->url($path))
            ->all());
    }

    public static function find(int|string $id): ?array
    {
        return static::findMany([(int) $id])->first();
    }

    public static function findMany(array $ids): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return collect();
        }

        return once(fn () => static::build(static::load($ids), app()->getLocale()));
    }

    public static function findBySku(string $sku): ?array
    {
        return static::products()->firstWhere('sku', $sku);
    }

    public static function findBySlug(string $slug): ?array
    {
        return static::products()->firstWhere('slug', $slug);
    }

    public static function findBySlugInAnyLocale(string $slug): ?array
    {
        return static::products()->first(fn (array $product) => in_array($slug, $product['slugs'], true));
    }

    public static function onSale(): Collection
    {
        return static::products()->filter(fn (array $product) => $product['old'] !== null)->values();
    }

    public static function similar(array $product, int $limit = 10): Collection
    {
        $products = static::products()->where('id', '!=', $product['id']);

        return $products->where('sub', $product['sub'])
            ->concat($products->where('cat', $product['cat'])->where('sub', '!=', $product['sub']))
            ->take($limit)
            ->values();
    }

    public static function visibleProducts(): Builder
    {
        return DB::table('products')
            ->where('products.is_active', true)
            ->whereNull('products.deleted_at')
            ->where('products.stock', '>', 0);
    }

    public static function categoryCounts(): Collection
    {
        return once(fn () => static::visibleProducts()
            ->whereNotNull('products.category_id')
            ->groupBy('products.category_id')
            ->selectRaw('products.category_id, count(*) as total')
            ->pluck('total', 'products.category_id')
            ->map(fn ($total) => (int) $total));
    }

    public static function searchCandidates(string $query, int $limit = 300): array
    {
        $words = array_slice(preg_split('/\s+/u', mb_strtolower(trim($query)), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 5);

        if ($words === []) {
            return [];
        }

        $locales = array_unique([app()->getLocale(), (string) config('app.fallback_locale')]);
        $candidates = static::visibleProducts()
            ->join('product_translations as t', fn ($join) => $join->on('t.product_id', '=', 'products.id')->whereIn('t.locale', $locales))
            ->leftJoin('brands as b', 'b.id', '=', 'products.brand_id');

        foreach ($words as $word) {
            $like = '%'.addcslashes(static::searchStem($word), '%_\\').'%';
            $candidates->where(fn ($query) => $query
                ->where('t.name', 'like', $like)
                ->orWhere('b.name', 'like', $like)
                ->orWhere('products.sku', 'like', $like));
        }

        return $candidates->distinct()->limit($limit)->pluck('products.id')->all();
    }

    public static function searchableCategories(): Collection
    {
        $nodes = CategoryTree::nodes();
        $counts = static::categoryCounts();

        return $nodes
            ->map(fn (array $node) => [
                'id' => $node['id'],
                'name' => $node['title'],
                'short' => $node['short'],
                'parent' => $node['parent_id'] ? ($nodes[$node['parent_id']]['short'] ?? null) : null,
                'depth' => CategoryTree::ancestors($node['id'])->count(),
                'count' => collect($node['subtree'])->sum(fn (int $id) => $counts->get($id, 0)),
                'url' => CategoryTree::url($node),
            ])
            ->filter(fn (array $category) => $category['count'] > 0)
            ->sortBy('depth')
            ->values();
    }

    public static function search(Collection $products, string $query): Collection
    {
        $term = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $query) ?? ''));

        if ($term === '') {
            return $products->values();
        }

        return $products
            ->map(fn (array $product) => [
                'product' => $product,
                'score' => static::searchScore("{$product['name']} {$product['brand']} {$product['subLabel']}", $term),
            ])
            ->filter(fn (array $match) => $match['score'] > 0)
            ->sortByDesc('score')
            ->pluck('product')
            ->values();
    }

    public static function searchScore(string $text, string $term): int
    {
        $haystack = mb_strtolower($text);
        $words = preg_split('/\s+/u', $haystack, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (collect($words)->contains(fn (string $word) => str_starts_with($word, $term))) {
            return 3;
        }

        if (str_contains($haystack, $term)) {
            return 2;
        }

        $termWords = preg_split('/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $termWords !== [] && collect($termWords)->every(fn (string $word) => str_contains($haystack, static::searchStem($word))) ? 1 : 0;
    }

    public static function searchStem(string $word): string
    {
        $length = mb_strlen($word);

        if ($length >= 6) {
            return mb_substr($word, 0, max(4, $length - 3));
        }

        return $length >= 4 ? mb_substr($word, 0, -1) : $word;
    }

    public static function categories(): Collection
    {
        $filled = fn (array $category) => $category['count'] > 0;

        return CategoryTree::roots()->map(fn (array $root) => [
            ...static::presentCategory($root),
            'groups' => CategoryTree::children($root['id'])->map(fn (array $group) => [
                ...static::presentCategory($group),
                'subs' => CategoryTree::children($group['id'])->map(fn (array $sub) => static::presentCategory($sub))->filter($filled)->values()->all(),
            ])->filter($filled)->values()->all(),
        ])->filter($filled)->values();
    }

    public static function productsIn(array $node, ?Collection $products = null): Collection
    {
        $codes = $node['subtree_codes'];
        $ids = $node['subtree'];

        return ($products ?? static::products())
            ->filter(fn (array $product) => in_array($product['categoryId'], $ids, true) || in_array($product['sub'], $codes, true))
            ->values();
    }

    public static function byTreeOrder(Collection $products): Collection
    {
        $order = CategoryTree::order();

        return $products->sortBy([
            fn (array $a, array $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0),
            fn (array $a, array $b) => ($order[$a['categoryId']] ?? PHP_INT_MAX) <=> ($order[$b['categoryId']] ?? PHP_INT_MAX),
        ])->values();
    }

    public static function sections(): Collection
    {
        $products = static::products();

        return CategoryTree::roots()
            ->where('show_on_main', true)
            ->values()
            ->map(function (array $root) use ($products) {
                $items = static::byTreeOrder(static::productsIn($root, $products));
                $chips = $items->pluck('sub')->filter()->countBy()->sortDesc()->keys()->take(self::SECTION_CHIPS)->map(fn ($sub) => (string) $sub)->values()->all();
                $shown = $items->take(self::SECTION_PRODUCTS)->pluck('id')
                    ->merge(collect($chips)->flatMap(fn (string $chip) => $items->filter(fn (array $product) => (string) $product['sub'] === $chip)->take(self::SECTION_CHIP_PRODUCTS)->pluck('id')))
                    ->flip();

                return [
                    ...static::presentCategory($root, $products),
                    'products' => $items->filter(fn (array $product) => $shown->has($product['id']))->values(),
                    'chips' => $chips,
                ];
            })
            ->filter(fn (array $section) => $section['products']->isNotEmpty())
            ->values();
    }

    public static function deal(): ?array
    {
        return static::products()
            ->filter(fn (array $product) => $product['deal'] !== null)
            ->sortBy(fn (array $product) => $product['deal']['position'])
            ->first();
    }

    public static function deals(): Collection
    {
        return static::products()
            ->filter(fn (array $product) => $product['deal'] !== null)
            ->sortBy(fn (array $product) => $product['deal']['position'])
            ->values();
    }

    public static function brands(): Collection
    {
        $counts = static::products()->countBy('brand');

        return collect(static::raw()['brands'])
            ->map(fn (array $brand) => ['name' => $brand['name'], 'count' => $counts->get($brand['name'], 0)])
            ->values();
    }

    public static function filterAttributes(?string $locale = null): Collection
    {
        return static::attributeMeta($locale)->filter(fn (array $attribute) => $attribute['filterable']);
    }

    public static function variantAttributes(?string $locale = null): Collection
    {
        return static::attributeMeta($locale)->filter(fn (array $attribute) => $attribute['variant']);
    }

    protected static function attributeMeta(?string $locale = null): Collection
    {
        $locale ??= app()->getLocale();

        return once(function () use ($locale) {
            return static::buildFilterAttributes($locale);
        });
    }

    public static function variantSwitchers(array $product): array
    {
        $siblings = blank($product['variantGroup'] ?? null)
            ? collect([$product])
            : static::products()
                ->where('variantGroup', $product['variantGroup'])
                ->sortBy('price')
                ->values();

        if ($siblings->doesntContain('id', $product['id'])) {
            $siblings->push($product);
        }

        $lineage = [$product['categoryId'], ...CategoryTree::ancestors((int) $product['categoryId'])->pluck('id')->all()];
        $attributes = static::variantAttributes()
            ->filter(fn (array $attribute) => $attribute['categories'] === [] || array_intersect($attribute['categories'], $lineage) !== []);

        if ($attributes->isEmpty()) {
            return [];
        }

        $here = array_intersect_key($product['variants'], $attributes->all());
        $switchers = [];

        foreach ($siblings as $sibling) {
            $isCurrent = $sibling['id'] === $product['id'];

            foreach ($sibling['variants'] as $code => $valueCode) {
                if (! $attributes->has($code)) {
                    continue;
                }

                $kept = collect($here)->filter(fn (string $chosen, string $other) => $other !== $code && ($sibling['variants'][$other] ?? null) === $chosen)->count();
                $existing = $switchers[$code][$valueCode] ?? null;

                if ($existing && ($existing['current'] || (! $isCurrent && $existing['kept'] >= $kept))) {
                    continue;
                }

                $value = $attributes[$code]['values'][$valueCode] ?? ['label' => $valueCode, 'hex' => null];

                $switchers[$code][$valueCode] = [
                    'code' => $valueCode,
                    'label' => $value['label'],
                    'hex' => $value['hex'],
                    'url' => $isCurrent ? null : $sibling['url'],
                    'current' => $isCurrent,
                    'inStock' => $sibling['inStock'],
                    'kept' => $kept,
                ];
            }
        }

        return collect($switchers)
            ->map(function (array $options, string $code) use ($attributes, $here): ?array {
                $attribute = $attributes[$code];
                $isColour = $attribute['type'] === 'color' || collect($options)->contains(fn (array $option) => filled($option['hex']));
                $besides = count($here) - (isset($here[$code]) ? 1 : 0);

                if (! $isColour) {
                    $options = array_filter($options, fn (array $option) => $option['current'] || $option['kept'] >= $besides);
                }

                if (count($options) < ($isColour ? 1 : 2)) {
                    return null;
                }

                $order = array_keys($attribute['values']);

                return [
                    'code' => $code,
                    'name' => $attribute['name'],
                    'colour' => $isColour,
                    'options' => collect($options)
                        ->sortBy(fn (array $option) => array_search($option['code'], $order, true) === false ? PHP_INT_MAX : array_search($option['code'], $order, true))
                        ->map(fn (array $option) => collect($option)->except('kept')->all())
                        ->values()
                        ->all(),
                ];
            })
            ->filter()
            ->sortByDesc('colour')
            ->values()
            ->all();
    }

    protected static function buildFilterAttributes(string $locale): Collection
    {
        $raw = static::raw();
        $fallback = config('app.fallback_locale');
        $pick = fn (Collection $rows, string $field) => (string) (($rows->firstWhere('locale', $locale) ?? $rows->firstWhere('locale', 'ka') ?? $rows->firstWhere('locale', $fallback) ?? $rows->first())[$field] ?? '');
        $names = collect($raw['filter_attribute_names'] ?? [])->groupBy('attribute_id');
        $labels = collect($raw['filter_value_labels'] ?? [])->groupBy('attribute_value_id');
        $categories = collect($raw['filter_attribute_categories'] ?? [])->groupBy('attribute_id');
        $values = collect($raw['filter_values'] ?? [])->groupBy('attribute_id');

        return collect($raw['filter_attributes'] ?? [])
            ->mapWithKeys(fn (array $attribute) => [$attribute['code'] => [
                'code' => $attribute['code'],
                'filterable' => (bool) ($attribute['is_filterable'] ?? true),
                'variant' => (bool) ($attribute['is_variant'] ?? false),
                'name' => $pick($names->get($attribute['id'], collect()), 'name') ?: $attribute['code'],
                'type' => $attribute['type'],
                'unit' => $attribute['unit'],
                'categories' => $categories->get($attribute['id'], collect())->pluck('category_id')->map(fn ($id) => (int) $id)->all(),
                'values' => $values->get($attribute['id'], collect())
                    ->mapWithKeys(fn (array $value) => [$value['code'] => [
                        'code' => $value['code'],
                        'label' => $pick($labels->get($value['id'], collect()), 'label') ?: $value['code'],
                        'hex' => $value['color_hex'],
                    ]])
                    ->all(),
            ]]);
    }

    public static function label(string $key): string
    {
        return CategoryTree::label($key);
    }

    public static function isFresh(?string $createdAt): bool
    {
        return $createdAt !== null && Carbon::parse($createdAt)->greaterThanOrEqualTo(now()->subDays(self::NEW_FOR_DAYS));
    }

    public static function money(int|float|string $amount): string
    {
        return number_format((float) $amount, 0, '.', ' ').' ₾';
    }

    public static function productTitle(?string $brand, ?string $name): string
    {
        $brand = trim((string) $brand);
        $name = trim((string) $name);

        if ($brand === '' || $name === '' || mb_stripos($name, $brand) !== false) {
            return $name !== '' ? $name : $brand;
        }

        return $brand.' '.$name;
    }

    protected static function productsFor(string $locale): Collection
    {
        return once(fn () => static::build(static::raw(), $locale));
    }

    protected static function build(array $raw, string $locale): Collection
    {
        $fallback = config('app.fallback_locale');
        $translations = collect($raw['translations'])->groupBy('product_id');
        $images = $raw['images'];
        $filters = $raw['filters'] ?? [];
        $attributes = collect($raw['filter_attributes'] ?? [])->keyBy('id');
        $valueMeta = collect($raw['filter_values'] ?? [])
            ->filter(fn (array $value) => $attributes->has($value['attribute_id']))
            ->mapWithKeys(fn (array $value) => [(int) $value['id'] => [
                'attribute' => $attributes[$value['attribute_id']]['code'],
                'value' => (string) $value['code'],
                'filterable' => (bool) $attributes[$value['attribute_id']]['is_filterable'],
                'variant' => (bool) $attributes[$value['attribute_id']]['is_variant'],
            ]]);
        $options = collect($raw['options'] ?? [])->groupBy('product_id');
        $deals = collect($raw['deals'] ?? [])
            ->filter(fn (array $deal) => HomeContent::isLive($deal['starts_at'], $deal['ends_at']))
            ->groupBy('product_id')
            ->map(fn (Collection $rows) => $rows->first());
        $campaignDiscounts = collect($raw['campaign_discounts'] ?? [])
            ->filter(fn (array $discount) => HomeContent::isLive($discount['starts_at'], $discount['ends_at']))
            ->groupBy('product_id')
            ->map(fn (Collection $rows) => (int) $rows->max('discount_percent'));
        $nodes = CategoryTree::nodes($locale);

        return collect($raw['products'])
            ->map(function (array $row) use ($raw, $translations, $images, $filters, $valueMeta, $options, $deals, $campaignDiscounts, $nodes, $locale, $fallback) {
                $node = $nodes->get($row['category_id']);

                if (! $node) {
                    return null;
                }

                $rows = $translations->get($row['id'], collect());
                $translation = $rows->firstWhere('locale', $locale) ?? $rows->firstWhere('locale', $fallback) ?? $rows->first();

                if (! $translation) {
                    return null;
                }

                $root = $node;
                while ($root['parent_id'] && $nodes->has($root['parent_id'])) {
                    $root = $nodes->get($root['parent_id']);
                }

                $basePrice = (float) $row['price'];
                $deal = $deals->get($row['id']);

                if ($deal) {
                    $row['old_price'] = max((float) $row['price'], (float) $row['old_price']);
                    $row['price'] = $deal['price'];
                }

                $campaignPercent = $campaignDiscounts->get($row['id']);

                if ($campaignPercent && ($campaignPrice = Campaign::discountedPrice($basePrice, $campaignPercent)) < (float) $row['price']) {
                    $row['old_price'] = max($basePrice, (float) $row['old_price']);
                    $row['price'] = $campaignPrice;
                }

                $picked = collect(explode(',', (string) ($filters[$row['id']] ?? '')))
                    ->map(fn (string $id) => $valueMeta->get((int) $id))
                    ->filter();

                return [
                    ...static::present($row, $translation, $rows->pluck('slug', 'locale')->all(), $node, $root, $images[$row['id']] ?? null),
                    'supplierSku' => $row['supplier_sku'],
                    'sold' => (int) ($raw['sold'][$row['id']] ?? 0),
                    'variantGroup' => $row['variant_group'] ?? null,
                    'variants' => $picked
                        ->filter(fn (array $value) => (bool) ($value['variant'] ?? false))
                        ->mapWithKeys(fn (array $value) => [$value['attribute'] => (string) $value['value']])
                        ->all(),
                    'filters' => $picked
                        ->filter(fn (array $value) => (bool) ($value['filterable'] ?? true))
                        ->groupBy('attribute')
                        ->map(fn (Collection $values) => $values->pluck('value')->unique()->values()->all())
                        ->all(),
                    'deal' => $deal ? [
                        'position' => (int) $deal['position'],
                        'text' => HomeContent::localized($deal['text']),
                        'endsAt' => $deal['ends_at'] ? Carbon::parse($deal['ends_at'])->toIso8601String() : null,
                    ] : null,
                    ...static::presentOptions($options->get($row['id'], collect()), $locale, $fallback, $campaignPercent),
                ];
            })
            ->filter()
            ->values();
    }

    protected static function present(array $row, array $translation, array $slugs, array $node, array $root, ?string $image): array
    {
        $price = (float) $row['price'];
        $old = $row['old_price'] !== null ? (float) $row['old_price'] : null;
        $old = $old && $old > $price ? $old : null;
        $isNew = (bool) $row['is_new'] || static::isFresh($row['created_at'] ?? null);
        $imageUrls = $image !== null ? [Storage::disk('public')->url($image)] : [];

        return [
            'id' => (int) $row['id'],
            'position' => (int) ($row['position'] ?? 0),
            'sku' => $row['sku'],
            'slug' => $translation['slug'],
            'slugs' => $slugs,
            'url' => route('product', ['slug' => $translation['slug']]),
            'categoryId' => (int) $row['category_id'],
            'cat' => $root['code'] ?? (string) $root['id'],
            'sub' => $node['code'] ?? (string) $node['id'],
            'subLabel' => $node['title'],
            'brand' => $row['brand_name'] ?? '',
            'name' => $translation['name'],
            'subtitle' => $translation['subtitle'],
            'price' => $price,
            'old' => $old,
            'priceLabel' => static::money($price),
            'oldLabel' => $old ? static::money($old) : null,
            'saveLabel' => $old ? static::money($old - $price) : null,
            'monthlyLabel' => static::money(ceil($price / static::INSTALLMENT_MONTHS)),
            'badge' => $old ? '−'.round((1 - $price / $old) * 100).'%' : ($isNew ? __('shop.badge_new') : null),
            'isNew' => ! $old && $isNew,
            'stock' => (int) $row['stock'],
            'inStock' => (int) $row['stock'] > 0,
            'image' => $imageUrls[0] ?? null,
            'images' => $imageUrls,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }

    protected static function presentOptions(Collection $options, string $locale, string $fallback, ?int $campaignPercent = null): array
    {
        $present = function (array $option) use ($locale, $fallback, $campaignPercent) {
            $labels = json_decode((string) $option['label'], true) ?: [];
            $price = $option['price'] !== null ? (float) $option['price'] : null;
            $old = $option['old_price'] !== null ? (float) $option['old_price'] : null;

            if ($campaignPercent && $price !== null) {
                $old = max((float) $old, $price);
                $price = Campaign::discountedPrice($price, $campaignPercent);
            }

            return [
                'id' => (int) $option['id'],
                'label' => $labels[$locale] ?? $labels[$fallback] ?? (string) reset($labels),
                'hex' => $option['hex'],
                'price' => $price,
                'old' => $old && $price !== null && $old > $price ? $old : null,
                'default' => (bool) $option['is_default'],
            ];
        };

        $colors = $options->where('type', 'color')->map($present)->values();
        $storages = $options->where('type', 'storage')->map($present)->values();

        return [
            'colors' => $colors->all(),
            'storages' => $storages->all(),
            'defaultColor' => max(0, (int) $colors->search(fn (array $option) => $option['default'])),
            'defaultStorage' => max(0, (int) $storages->search(fn (array $option) => $option['default'])),
        ];
    }

    public static function resolveOptions(array $product, array $optionIds): array
    {
        $chosen = [];
        $price = $product['price'];
        $old = $product['old'];

        foreach (['colors', 'storages'] as $group) {
            if (! $product[$group]) {
                continue;
            }

            $option = collect($product[$group])->first(fn (array $item) => in_array($item['id'], $optionIds, true))
                ?? $product[$group][$group === 'colors' ? $product['defaultColor'] : $product['defaultStorage']];

            $chosen[] = $option;

            if ($option['price'] !== null) {
                $price = $option['price'];
                $old = $option['old'];
            }
        }

        return [
            'ids' => collect($chosen)->pluck('id')->sort()->values()->all(),
            'labels' => collect($chosen)->pluck('label')->all(),
            'price' => $price,
            'old' => $old,
        ];
    }

    protected static function presentCategory(array $node, ?Collection $products = null): array
    {
        $counts = $products === null ? static::categoryCounts() : null;

        return [
            'id' => $node['code'] ?? (string) $node['id'],
            'key' => $node['id'],
            'name' => $node['title'],
            'short' => $node['short'],
            'icon' => $node['icon'],
            'url' => CategoryTree::url($node),
            'count' => $counts !== null
                ? (int) collect($node['subtree'])->sum(fn (int $id) => $counts->get($id, 0))
                : static::productsIn($node, $products)->count(),
        ];
    }

    protected static function raw(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => static::load());
    }

    protected static function load(?array $ids = null): array
    {
        $whole = $ids === null;
        $live = fn () => static::visibleProducts()->select('products.id')
            ->when(! $whole, fn ($query) => $query->whereIn('products.id', $ids));

        return [
            'products' => DB::table('products')
                ->leftJoin('brands', fn ($join) => $join->on('brands.id', '=', 'products.brand_id')->whereNull('brands.deleted_at'))
                ->where('products.is_active', true)
                ->whereNull('products.deleted_at')
                ->where('products.stock', '>', 0)
                ->when(! $whole, fn ($query) => $query->whereIn('products.id', $ids))
                ->orderBy('products.position')
                ->orderByDesc('products.id')
                ->get([
                    'products.id', 'products.category_id', 'products.sku', 'products.supplier_sku', 'products.price', 'products.old_price',
                    'products.stock', 'products.is_new', 'products.variant_group', 'products.position', 'products.created_at', 'products.updated_at', 'brands.name as brand_name',
                ])
                ->map(fn ($row) => (array) $row)
                ->all(),
            'translations' => DB::table('product_translations')
                ->whereIn('product_id', $live())
                ->get(['product_id', 'locale', 'name', 'slug', 'subtitle'])
                ->map(fn ($row) => (array) $row)
                ->all(),
            'images' => DB::table('product_images as pi')
                ->whereIn('pi.product_id', $live())
                ->whereRaw('pi.id = (select first.id from product_images as first where first.product_id = pi.product_id order by first.position, first.id limit 1)')
                ->pluck('pi.path', 'pi.product_id')
                ->all(),
            'brands' => ! $whole ? [] : DB::table('brands')
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->where('show_on_home', true)
                ->orderBy('home_position')
                ->orderBy('name')
                ->limit(Brand::HOME_LIMIT)
                ->get(['id', 'name'])
                ->map(fn ($row) => (array) $row)
                ->all(),
            'filters' => DB::table('attribute_value_product as avp')
                ->whereIn('avp.product_id', $live())
                ->groupBy('avp.product_id')
                ->selectRaw('avp.product_id, group_concat(avp.attribute_value_id) as ids')
                ->pluck('ids', 'avp.product_id')
                ->all(),
            'filter_attributes' => DB::table('attributes')
                ->whereNull('deleted_at')
                ->where(fn ($query) => $query->where('is_filterable', true)->orWhere('is_variant', true))
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'code', 'type', 'unit', 'sort_order', 'is_filterable', 'is_variant'])
                ->map(fn ($row) => (array) $row)
                ->all(),
            'filter_attribute_names' => ! $whole ? [] : DB::table('attribute_translations as t')
                ->join('attributes as a', 'a.id', '=', 't.attribute_id')
                ->whereNull('a.deleted_at')
                ->where(fn ($query) => $query->where('a.is_filterable', true)->orWhere('a.is_variant', true))
                ->get(['t.attribute_id', 't.locale', 't.name'])
                ->map(fn ($row) => (array) $row)
                ->all(),
            'filter_attribute_categories' => ! $whole ? [] : DB::table('attribute_category as ac')
                ->join('attributes as a', 'a.id', '=', 'ac.attribute_id')
                ->whereNull('a.deleted_at')
                ->where(fn ($query) => $query->where('a.is_filterable', true)->orWhere('a.is_variant', true))
                ->get(['ac.attribute_id', 'ac.category_id'])
                ->map(fn ($row) => (array) $row)
                ->all(),
            'filter_values' => DB::table('attribute_values as av')
                ->when(! $whole, fn ($query) => $query->whereIn('av.id', DB::table('attribute_value_product')->select('attribute_value_id')->whereIn('product_id', $ids)))
                ->join('attributes as a', 'a.id', '=', 'av.attribute_id')
                ->whereNull('av.deleted_at')
                ->whereNull('a.deleted_at')
                ->where(fn ($query) => $query->where('a.is_filterable', true)->orWhere('a.is_variant', true))
                ->orderBy('av.sort_order')
                ->orderBy('av.id')
                ->get(['av.id', 'av.attribute_id', 'av.code', 'av.color_hex', 'av.sort_order'])
                ->map(fn ($row) => (array) $row)
                ->all(),
            'filter_value_labels' => ! $whole ? [] : DB::table('attribute_value_translations as t')
                ->join('attribute_values as av', 'av.id', '=', 't.attribute_value_id')
                ->join('attributes as a', 'a.id', '=', 'av.attribute_id')
                ->whereNull('av.deleted_at')
                ->whereNull('a.deleted_at')
                ->where(fn ($query) => $query->where('a.is_filterable', true)->orWhere('a.is_variant', true))
                ->get(['t.attribute_value_id', 't.locale', 't.label'])
                ->map(fn ($row) => (array) $row)
                ->all(),
            'campaign_discounts' => DB::table('campaign_product as cp')
                ->join('campaigns as c', 'c.id', '=', 'cp.campaign_id')
                ->whereNull('c.deleted_at')
                ->where('c.is_active', true)
                ->where('c.discount_percent', '>', 0)
                ->when(! $whole, fn ($query) => $query->whereIn('cp.product_id', $ids))
                ->get(['cp.product_id', 'c.discount_percent', 'c.starts_at', 'c.ends_at'])
                ->map(fn ($row) => (array) $row)
                ->all(),
            'deals' => DB::table('deals')
                ->whereNull('deleted_at')
                ->when(! $whole, fn ($query) => $query->whereIn('product_id', $ids))
                ->where('is_active', true)
                ->orderBy('position')
                ->orderBy('id')
                ->get(['product_id', 'price', 'text', 'position', 'starts_at', 'ends_at'])
                ->map(fn ($row) => (array) $row)
                ->all(),
            'options' => DB::table('product_options')
                ->whereIn('product_id', $live())
                ->orderBy('position')
                ->orderBy('id')
                ->get(['id', 'product_id', 'type', 'label', 'hex', 'price', 'old_price', 'is_default'])
                ->map(fn ($row) => (array) $row)
                ->all(),
            'sold' => DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereNotNull('order_items.product_id')
                ->when(! $whole, fn ($query) => $query->whereIn('order_items.product_id', $ids))
                ->whereNull('orders.deleted_at')
                ->where('orders.status', '!=', 'cancelled')
                ->groupBy('order_items.product_id')
                ->selectRaw('order_items.product_id, sum(order_items.quantity) as quantity')
                ->pluck('quantity', 'order_items.product_id')
                ->map(fn ($quantity) => (int) $quantity)
                ->all(),
        ];
    }
}
