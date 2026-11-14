<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;

class RecentlyViewed
{
    public const COOKIE = 'recently_viewed';

    public const KEEP = 16;

    public const MINUTES = 60 * 24 * 60;

    public static function ids(): array
    {
        $queued = Cookie::queued(self::COOKIE);
        $raw = $queued ? $queued->getValue() : request()->cookie(self::COOKIE);

        return collect(explode(',', (string) $raw))
            ->map(fn (string $id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->take(self::KEEP)
            ->values()
            ->all();
    }

    public static function push(int $productId): void
    {
        $ids = collect([$productId, ...static::ids()])->unique()->take(self::KEEP)->implode(',');

        Cookie::queue(self::COOKIE, $ids, self::MINUTES);
    }

    public static function products(?int $except = null, int $limit = 10): Collection
    {
        return collect(static::ids())
            ->reject(fn (int $id) => $id === $except)
            ->map(fn (int $id) => Catalog::find($id))
            ->filter()
            ->take($limit)
            ->values();
    }
}
