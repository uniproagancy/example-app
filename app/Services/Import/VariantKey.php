<?php

namespace App\Services\Import;

use App\Support\Slug;
use Illuminate\Support\Str;

class VariantKey
{
    public const MAX_LENGTH = 64;

    public const AXES = [
        'color', 'colour', 'ფერი',
        'internal memory', 'memory', 'ram', 'ram memory', 'built-in memory',
        'storage', 'capacity', 'volume', 'size',

        'feri', 'shida mekhsiereba', 'mekhsiereba', 'operatiuli mekhsiereba',
        'ram motsuloba', 'motsuloba', 'zoma',
    ];

    protected const UNITS = 'gb|tb|mb';

    public const COLOURS = [
        'black', 'white', 'grey', 'gray', 'silver', 'gold', 'golden',
        'blue', 'green', 'red', 'pink', 'purple', 'violet', 'yellow',
        'orange', 'brown', 'beige', 'ivory', 'bronze', 'copper', 'navy',
        'teal', 'cyan', 'magenta', 'burgundy', 'olive', 'khaki', 'jade',
        'amber', 'rose', 'lilac', 'peach', 'turquoise', 'coral', 'mint',
        'lavender', 'charcoal', 'graphite', 'obsidian', 'onyx', 'slate',
        'titanium', 'titan', 'midnight', 'starlight', 'moonlight', 'phantom',
        'forest', 'aurora', 'velvet', 'sand', 'sky', 'mocha', 'taupe',
        'platinum', 'sapphire', 'emerald', 'lime', 'aqua', 'indigo',
        'ultramarine', 'cosmic', 'nebula', 'mystic',

        'shavi', 'tetri', 'nacrisperi', 'vertskhlisperi', 'okrosperi',
        'lurji', 'mtsvane', 'tsiteli', 'vardisperi', 'vardisferi',
        'iisperi', 'iisferi', 'qviteli', 'narinjisperi', 'kavisperi',
        'tsisperi', 'bezhi', 'shindisperi',
    ];

    public static function base(string $name, array $distinguishing = []): string
    {
        $base = self::normalise($name);

        foreach ($distinguishing as $value) {
            $value = self::normalise((string) $value);

            if ($value === '' || $value === $base) {
                continue;
            }

            $base = (string) preg_replace('/(?<=^|\s)'.preg_quote($value, '/').'(?=\s|$)/u', ' ', $base);
        }

        $base = (string) preg_replace('/(?<=^|\s)\d+(?:[.,]\d+)?\s?(?:'.self::UNITS.')(?=\s|$)/u', ' ', $base);

        foreach ($distinguishing as $value) {
            $inches = self::screenSize((string) $value);

            if ($inches !== null) {
                $base = (string) preg_replace('/(?<!\d)'.$inches.'(?!\d)/u', ' ', $base);
            }
        }

        $base = (string) preg_replace(
            '/(?<=^|\s)(?:'.implode('|', self::COLOURS).')(?=\s|$)/u', ' ', $base);

        return trim((string) preg_replace('/\s+/u', ' ', $base));
    }

    public static function fromSpecs(string $supplierCode, ?string $brandName, string $name, array $specs): ?string
    {
        return self::make($supplierCode, $brandName, $name, self::axisValues($specs));
    }

    public static function make(string $supplierCode, ?string $brandName, string $name, array $distinguishing): ?string
    {
        $base = self::base($name, $distinguishing);

        if (mb_strlen($base) < 4 || ! str_contains($base, ' ')) {
            return null;
        }

        $brand = self::normalise((string) $brandName);

        $slug = self::slug($brand !== '' && ! str_starts_with($base, $brand)
            ? $brand.' '.$base
            : $base);

        if ($slug === '') {
            return null;
        }

        $prefix = self::slug($supplierCode);
        $room = self::MAX_LENGTH - mb_strlen($prefix) - 1;

        if (mb_strlen($slug) > $room) {
            $slug = rtrim(mb_substr($slug, 0, $room - 7), '-').'-'.mb_substr(sha1($slug), 0, 6);
        }

        return $prefix.'-'.$slug;
    }

    public static function axisValues(array $specs): array
    {
        $values = [];

        foreach ($specs as $spec) {
            if (! self::isAxis((string) ($spec['name'] ?? ''))) {
                continue;
            }

            $value = trim((string) ($spec['value'] ?? ''));

            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    public static function screenSize(string $value): ?string
    {
        $value = trim(mb_strtolower($value));

        if (! preg_match('/^(\d{2,3})\s*(?:"|”|\x{201D}|inch|inches|inc|იანი|სმ|cm)?$/u', $value, $m)) {
            return null;
        }

        return $m[1];
    }

    public static function isAxis(string $specName): bool
    {
        $name = self::normalise($specName);

        if ($name === '') {
            return false;
        }

        foreach (self::AXES as $axis) {
            if ($name === $axis || str_ends_with($name, ' '.$axis)) {
                return true;
            }
        }

        return false;
    }

    public static function normalise(string $value): string
    {
        $value = mb_strtolower(trim($value));

        $value = str_replace('+', ' plus ', $value);

        $value = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    protected static function slug(string $value): string
    {
        return Slug::make($value) ?: Str::slug($value);
    }
}
