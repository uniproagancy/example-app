<?php

namespace App\Filament\Support;

use Illuminate\Support\Collection;

class OptionSearch
{
    public static function suggest(array $options, ?string $name, int $limit = 8, array $aliases = []): array
    {
        $stems = static::stems((string) $name);

        if ($stems->isEmpty()) {
            return [];
        }

        return collect($options)
            ->map(function (string $label, int|string $value) use ($stems, $aliases): int {
                $label = mb_strtolower($label);
                $parts = preg_split('/\s*[›·]\s*/u', $label);
                $leaf = ($parts === false || $parts === [] ? $label : end($parts)).' '.mb_strtolower((string) ($aliases[$value] ?? ''));

                return $stems->map(fn (string $stem, int $index) => ($index === $stems->count() - 1 ? 2 : 1) * match (true) {
                    str_contains($leaf, $stem) => 3,
                    str_contains($label, $stem) => 1,
                    default => 0,
                })->sum();
            })
            ->filter(fn (int $score) => $score > 0)
            ->sortDesc()
            ->take($limit)
            ->map(fn (int $score, int|string $value) => $options[$value])
            ->all();
    }

    public static function search(array $options, ?string $search, int $limit = 50, array $aliases = []): array
    {
        $words = collect(preg_split('/\s+/u', mb_strtolower(trim((string) $search)), -1, PREG_SPLIT_NO_EMPTY));

        if ($words->isEmpty()) {
            return array_slice($options, 0, $limit, true);
        }

        return collect($options)
            ->filter(function (string $label, int|string $value) use ($words, $aliases): bool {
                $haystack = mb_strtolower($label.' '.($aliases[$value] ?? ''));

                return $words->every(fn (string $word) => str_contains($haystack, $word));
            })
            ->take($limit)
            ->all();
    }

    protected static function stems(string $name): Collection
    {
        return collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($name), -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn (string $word) => mb_strlen($word) >= 3)
            ->map(fn (string $word) => mb_substr($word, 0, max(3, min(5, mb_strlen($word) - 1))))
            ->unique()
            ->values();
    }
}
