<?php

namespace App\Support;

class Palette
{
    public const COLOURS = [
        'black' => [
            'black', 'onyx', 'obsidian', 'jet', 'ebony', 'midnight', 'noir',
            'carbon',
            'შავი', 'შავ',
        ],
        'white' => [
            'white', 'snow', 'starlight', 'moonlight', 'porcelain',
            'თეთრი', 'თეთრ', 'თოვლისფერი',
        ],
        'grey' => [
            'grey', 'gray', 'graphite', 'charcoal', 'slate', 'titanium',
            'titan', 'anthracite', 'gunmetal', 'smoke', 'smoky',
            'ნაცრისფერი', 'ნაცრისფერ', 'ანტრაციტი', 'გრაფიტი',
            'ნახშირისფერი',
        ],
        'silver' => [
            'silver', 'platinum', 'inox', 'chrome',
            'ვერცხლისფერი', 'ვერცხლისფერ', 'ფოლად', 'პლატინა', 'ინოქს',
        ],
        'gold' => [
            'gold', 'golden', 'bronze', 'brass',
            'ოქროსფერი', 'ოქროსფერ', 'ბრონზ',
        ],
        'blue' => [
            'blue', 'navy', 'cyan', 'azure', 'sapphire', 'indigo',
            'ultramarine', 'aqua', 'turquoise', 'teal', 'denim',
            'ლურჯი', 'ლურჯ', 'ცისფერი', 'ცისფერ', 'ზღვისფერი',
        ],
        'green' => [
            'green', 'olive', 'mint', 'lime', 'emerald', 'jade', 'khaki',
            'sage',
            'მწვანე', 'ზეთისხილისფერი',
        ],
        'red' => [
            'red', 'burgundy', 'crimson', 'scarlet', 'ruby', 'wine', 'maroon',
            'berry',
            'წითელი', 'წითელ', 'შინდისფერი', 'შინდისფერ', 'ალისფერი',
        ],
        'pink' => [
            'pink', 'rose', 'coral', 'magenta', 'fuchsia', 'peach', 'salmon',
            'blush',
            'ვარდისფერი', 'ვარდისფერ', 'ატმისფერი', 'ფუქსინ', 'მაგენტ',
        ],
        'purple' => [
            'purple', 'violet', 'lilac', 'lavender', 'lavander', 'mauve',
            'plum',
            'იასამნისფერი', 'იისფერი', 'იისპერი', 'მეწამული',
        ],
        'yellow' => [
            'yellow', 'amber', 'mustard', 'lemon',
            'ყვითელი', 'ყვითელ',
        ],
        'orange' => [
            'orange', 'tangerine', 'apricot',
            'ნარინჯისფერი', 'ნარინჯისფერ', 'ფორთოხლისფერი',
        ],
        'brown' => [
            'brown', 'mocha', 'taupe', 'chocolate', 'coffee', 'walnut',
            'wood', 'copper', 'terracotta', 'tan',
            'ყავისფერი', 'ყავისფერ', 'ხისფერი', 'ხისფერ', 'აგურისფერი',
            'სპილენძისფერი',
        ],
        'beige' => [
            'beige', 'cream', 'ivory', 'sand', 'champagne', 'nude',
            'კრემისფერი', 'კრემისფერ', 'ქვიშისფერი', 'ბეჟი',
        ],
        'transparent' => [
            'transparent', 'transparant', 'clear', 'crystal',
            'გამჭვირვალე', 'გამჭირვალე', 'უფერული',
        ],
    ];

    protected const COMPOUNDS = [
        'rose gold' => 'gold',
        'pink gold' => 'gold',
        'sand gold' => 'gold',
        'mint green' => 'green',
        'olive green' => 'green',
        'terra cotta' => 'brown',
        'coral blue' => 'blue',
        'coral red' => 'red',
        'peach blue' => 'blue',
    ];

    protected const NOT_COLOURS = [
        'black shark', 'red magic', 'blue yeti', 'green lion', 'red bull',
        'e blue', 'red dragon', 'black decker', 'blue ocean',
    ];

    protected const PLAIN = [
        'black', 'white', 'grey', 'gray', 'silver', 'gold', 'blue', 'green',
        'red', 'pink', 'purple', 'violet', 'yellow', 'orange', 'brown', 'beige',
    ];

    protected const STEM_LENGTH = 5;

    protected static ?array $index = null;

    public static function baseOf(?string $shade): ?string
    {

        $shade = Text::words($shade, '\p{L}');

        if ($shade === '') {
            return null;
        }

        foreach (self::COMPOUNDS as $pair => $base) {
            if (str_contains($shade, $pair)) {
                return $base;
            }
        }

        foreach (preg_split('/\s+/u', $shade) ?: [] as $word) {
            if ($base = self::wordFor($word)) {
                return $base;
            }
        }

        return null;
    }

    public static function named(?string $name, ?string $brand = null): ?string
    {
        $text = Text::words($name, '\p{L}');

        foreach (array_merge(self::NOT_COLOURS, array_filter([Text::words($brand, '\p{L}')])) as $phrase) {
            $text = (string) preg_replace('/(?<=^|\s)'.preg_quote($phrase, '/').'(?=\s|$)/u', ' ', $text);
        }

        if ($text === '') {
            return null;
        }

        $found = [];

        foreach (self::COMPOUNDS as $pair => $base) {
            if (preg_match('/(?<=^|\s)'.preg_quote($pair, '/').'(?=\s|$)/u', $text)) {
                $found[$base] = true;
                $text = (string) preg_replace('/(?<=^|\s)'.preg_quote($pair, '/').'(?=\s|$)/u', ' ', $text);
            }
        }

        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            $georgian = (bool) preg_match('/\p{Georgian}/u', $word);

            if (! $georgian && ! in_array($word, self::PLAIN, true)) {
                continue;
            }

            if ($base = self::wordFor($word)) {
                $found[$base] = true;
            }
        }

        return count($found) === 1 ? (string) array_key_first($found) : null;
    }

    public static function label(string $base): string
    {
        return __("colour.{$base}");
    }

    public static function names(): array
    {
        return array_keys(self::COLOURS);
    }

    protected static function wordFor(string $word): ?string
    {
        self::$index ??= self::buildIndex();

        if (isset(self::$index[$word])) {
            return self::$index[$word];
        }

        if (! preg_match('/\p{Georgian}/u', $word)) {
            return null;
        }

        foreach (self::$index as $known => $base) {
            if (mb_strlen((string) $known) >= self::STEM_LENGTH && str_starts_with($word, (string) $known)) {
                return $base;
            }
        }

        return null;
    }

    protected static function buildIndex(): array
    {
        $index = [];

        foreach (self::COLOURS as $base => $words) {
            foreach ($words as $word) {
                $index[$word] ??= $base;
            }
        }

        uksort($index, fn ($a, $b) => mb_strlen((string) $b) <=> mb_strlen((string) $a));

        return $index;
    }
}
