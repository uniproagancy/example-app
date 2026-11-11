<?php

namespace App\Support;

class Cart
{
    public const SESSION_KEY = 'cart';

    public const MAX_QUANTITY = 99;

    public static function items(): array
    {
        return static::sanitize((array) session(self::SESSION_KEY, []));
    }

    public static function store(array $items): array
    {
        $items = static::sanitize($items);
        session([self::SESSION_KEY => $items]);

        return $items;
    }

    public static function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public static function key(int $productId, array $optionIds): string
    {
        return $optionIds ? $productId.':'.implode(',', $optionIds) : (string) $productId;
    }

    public static function resolve(string $key): ?array
    {
        [$productId, $optionPart] = array_pad(explode(':', $key, 2), 2, '');

        if (! ctype_digit($productId) || ! ($product = Catalog::find((int) $productId))) {
            return null;
        }

        $optionIds = $optionPart === '' ? [] : array_map('intval', explode(',', $optionPart));
        $resolved = Catalog::resolveOptions($product, $optionIds);

        if (static::key($product['id'], $resolved['ids']) !== $key) {
            return null;
        }

        return [$product, $resolved];
    }

    public static function lines(array $items): array
    {
        return collect($items)
            ->map(function (int $quantity, int|string $key) {
                [$product, $resolved] = static::resolve((string) $key) ?? [null, null];

                if (! $product) {
                    return null;
                }

                return [
                    'key' => (string) $key,
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'variant' => implode(' · ', $resolved['labels']),
                    'options' => $resolved['labels'],
                    'url' => $product['url'],
                    'image' => $product['image'],
                    'icon' => $product['cat'],
                    'initial' => mb_substr($product['brand'], 0, 1),
                    'quantity' => $quantity,
                    'stock' => $product['stock'],
                    'price' => $resolved['price'],
                    'lineLabel' => Catalog::money($resolved['price'] * $quantity),
                    'subtotal' => $resolved['price'] * $quantity,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public static function shippingFor(float $subtotal): float
    {
        if ($subtotal <= 0) {
            return 0;
        }

        return $subtotal >= Catalog::FREE_SHIPPING_FROM ? 0 : Catalog::SHIPPING_PRICE;
    }

    public static function sanitize(array $items): array
    {
        return collect($items)
            ->filter(fn ($quantity, $key) => is_int($quantity) && $quantity > 0 && static::resolve((string) $key) !== null)
            ->map(fn (int $quantity) => min($quantity, self::MAX_QUANTITY))
            ->all();
    }
}
