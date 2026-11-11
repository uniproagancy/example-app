<?php

namespace App\Support;

use App\Models\HomeBlock;
use App\Models\Promo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Once;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class HomeContent
{
    public const CACHE_KEY = 'home.content';

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        Once::flush();
    }

    public static function blocks(): Collection
    {
        $rows = collect(static::raw()['blocks']);

        if ($rows->isEmpty()) {
            return collect(HomeBlock::KEYS)->map(fn (string $key) => ['key' => $key, 'title' => null, 'kicker' => null]);
        }

        return $rows->where('is_active', true)
            ->filter(fn (array $row) => in_array($row['key'], HomeBlock::KEYS, true))
            ->map(fn (array $row) => [
                'key' => $row['key'],
                'title' => static::localized($row['title']),
                'kicker' => static::localized($row['kicker']),
            ])
            ->values();
    }

    public static function perks(): Collection
    {
        return collect(static::raw()['perks'])->map(fn (array $row) => [
            'icon' => $row['icon'],
            'title' => static::localized($row['title']),
            'text' => static::localized($row['text']),
        ]);
    }

    public static function promos(): Collection
    {
        return collect(static::raw()['promos'])
            ->filter(fn (array $row) => static::isLive($row['starts_at'], $row['ends_at']))
            ->map(fn (array $row) => [
                'badge' => static::localized($row['badge']),
                'title' => static::localized($row['title']),
                'text' => static::localized($row['text']),
                'note' => static::localized($row['note']),
                'href' => static::href($row['category_id'], $row['url']),
                'theme' => in_array($row['theme'], Promo::THEMES, true) ? $row['theme'] : 'navy',
                'icon' => $row['icon'],
            ])
            ->values();
    }

    public static function isLive(?string $startsAt, ?string $endsAt): bool
    {
        return (! $startsAt || Carbon::parse($startsAt)->isPast()) && (! $endsAt || Carbon::parse($endsAt)->isFuture());
    }

    public static function localized(array|string|null $values): ?string
    {
        if (is_string($values)) {
            $values = json_decode($values, true);
        }

        if (! $values) {
            return null;
        }

        return $values[app()->getLocale()] ?? $values[config('app.fallback_locale')] ?? (string) reset($values);
    }

    protected static function href(?int $categoryId, ?string $url): ?string
    {
        if ($categoryId && ($node = CategoryTree::find($categoryId))) {
            return CategoryTree::url($node);
        }

        if ($url && str_starts_with($url, '/')) {
            return LaravelLocalization::localizeUrl($url);
        }

        return $url ?: null;
    }

    protected static function raw(): array
    {
        return once(fn () => Cache::rememberForever(self::CACHE_KEY, fn () => [
            'blocks' => DB::table('home_blocks')
                ->orderBy('position')
                ->orderBy('id')
                ->get(['key', 'title', 'kicker', 'is_active'])
                ->map(fn ($row) => [...(array) $row, 'is_active' => (bool) $row->is_active])
                ->all(),
            'perks' => DB::table('perks')
                ->where('is_active', true)
                ->orderBy('position')
                ->orderBy('id')
                ->get(['icon', 'title', 'text'])
                ->map(fn ($row) => (array) $row)
                ->all(),
            'promos' => DB::table('promos')
                ->where('is_active', true)
                ->orderBy('position')
                ->orderBy('id')
                ->get(['badge', 'title', 'text', 'note', 'category_id', 'url', 'theme', 'icon', 'starts_at', 'ends_at'])
                ->map(fn ($row) => [...(array) $row, 'category_id' => $row->category_id ? (int) $row->category_id : null])
                ->all(),
        ]));
    }
}
