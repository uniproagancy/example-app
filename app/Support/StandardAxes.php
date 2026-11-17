<?php

namespace App\Support;

class StandardAxes
{
    public const STORAGE = 'storage';

    public const SCREEN = 'screen-size';

    public const CAPACITY = 'capacity';

    public const SORT_ORDER = 10;

    protected const RANGE = '/(?:მდე|დან|~|≤|≥|±|\bup to\b|\bmax\b|microsd|micro sd|ბარათ|sodimm|\bddr|\bram\b)/u';

    protected const AFTER = '/^\s*(?:-|–|—|\/|\+|x|×|\*)\s*\d/u';

    /**
     * @return array<string, array{ka: string, en: string, sources: string, names: bool}>
     */
    public static function axes(): array
    {
        return [
            self::STORAGE => [
                'ka' => 'მეხსიერება',
                'en' => 'Storage',
                'sources' => '/^(?:shida-mekhsiereba|internal-memory|mekhsiereba|mekhsierebis-tevadoba|storage-capacity|ssd-motsuloba|shida-motsuloba|motsuloba|tevadoba)$/',
                'names' => true,
            ],
            self::SCREEN => [
                'ka' => 'ეკრანის დიაგონალი',
                'en' => 'Screen size',
                'sources' => '/^(?:diagonali|ekranis-zoma|ekranis-diagonali|lcd-diagonali|diagonaluri-zoma|diagonalis-zoma|display-size|screen)$/',
                'names' => true,
            ],
            self::CAPACITY => [
                'ka' => 'ტევადობა',
                'en' => 'Capacity',
                'sources' => '/^(?:motsuloba|jamuri-motsuloba|saerto-motsuloba|mtliani-motsuloba|tevadoba|jamis-motsuloba|shida-motsuloba|maksimaluri-chatvirtva)$/',
                'names' => false,
            ],
        ];
    }

    public static function isSource(string $axis, string $attributeCode): bool
    {
        return $attributeCode !== $axis && (bool) preg_match(static::axes()[$axis]['sources'], $attributeCode);
    }

    /**
     * @return array{code: string, ka: string, en: string, sort: int}|null
     */
    public static function parse(string $axis, string $value, bool $dedicated = true): ?array
    {
        $text = static::clean($value);

        if ($text === '' || preg_match(self::RANGE, $text)) {
            return null;
        }

        return match ($axis) {
            self::STORAGE => static::storage($text),
            self::SCREEN => static::screen($text, $dedicated),
            self::CAPACITY => static::capacity($text),
            default => null,
        };
    }

    /**
     * @return array{code: string, ka: string, en: string, sort: int}|null
     */
    public static function fromName(string $axis, string $name): ?array
    {
        $text = static::clean($name);

        if ($axis === self::STORAGE) {
            preg_match_all('/(?<![\d.])(\d{1,4})\s?(gb|tb|გბ|ტბ)(?![a-z\d])/u', $text, $found, PREG_SET_ORDER);
            $sizes = array_map(fn (array $match) => static::gigabytes((float) $match[1], $match[2]), $found);

            if (preg_match('/(?<![\d.])\d{1,2}\s?\/\s?(\d{2,4})\s?(gb|tb|გბ|ტბ)(?![a-z\d])/u', $text, $pair)) {
                $sizes[] = static::gigabytes((float) $pair[1], $pair[2]);
            }

            $sizes = array_filter($sizes, fn (int $gigabytes) => $gigabytes >= 32);

            return $sizes === [] ? null : static::storageValue(max($sizes));
        }

        if ($axis === self::SCREEN && preg_match_all('/(?<![\d.])(\d{2,3}(?:\.\d)?)\s?(?:"|”|″|\'\'|inch(?:es)?\b|ინჩ)/u', $text, $found)) {
            $sizes = array_unique($found[1]);

            return count($sizes) === 1 ? static::screenValue((float) $sizes[0]) : null;
        }

        return null;
    }

    protected static function clean(string $value): string
    {
        $value = mb_strtolower(trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $value = (string) preg_replace('/(?<=\d),(?=\d)/u', '.', $value);
        $value = str_replace(["\u{00A0}", "\u{2009}", "\u{202F}"], ' ', $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * @return array{code: string, ka: string, en: string, sort: int}|null
     */
    protected static function storage(string $text): ?array
    {
        if (! preg_match('/^(\d+(?:\.\d+)?)\s?(gb|tb|გბ|ტბ)(?![a-z])(.*)$/u', $text, $match) || preg_match(self::AFTER, $match[3])) {
            return null;
        }

        return static::storageValue(static::gigabytes((float) $match[1], $match[2]));
    }

    protected static function gigabytes(float $amount, string $unit): int
    {
        return (int) round(in_array($unit, ['tb', 'ტბ'], true) ? $amount * 1024 : $amount);
    }

    /**
     * @return array{code: string, ka: string, en: string, sort: int}|null
     */
    protected static function storageValue(int $gigabytes): ?array
    {
        if ($gigabytes < 8 || $gigabytes > 16384) {
            return null;
        }

        $terabytes = match (true) {
            $gigabytes >= 1000 && $gigabytes % 1024 === 0 => $gigabytes / 1024,
            $gigabytes >= 1000 && $gigabytes % 1000 === 0 => $gigabytes / 1000,
            default => null,
        };

        if ($terabytes !== null) {
            $label = $terabytes.' TB';

            return ['code' => $terabytes.'-tb', 'ka' => $label, 'en' => $label, 'sort' => $terabytes * 1024];
        }

        $label = $gigabytes.' GB';

        return ['code' => $gigabytes.'-gb', 'ka' => $label, 'en' => $label, 'sort' => $gigabytes];
    }

    /**
     * @return array{code: string, ka: string, en: string, sort: int}|null
     */
    protected static function screen(string $text, bool $dedicated): ?array
    {
        if (! preg_match('/^(\d{1,3}(?:\.\d{1,2})?)\s?("|”|″|\'\'|inch(?:es)?\b|ინჩ\S*|)(.*)$/u', $text, $match) || preg_match(self::AFTER, $match[3])) {
            return null;
        }

        $rest = trim($match[3]);

        if ($match[2] === '' && (! $dedicated || ($rest !== '' && ! preg_match('/^(?:wuxga|fhd|uhd|qhd|4k|8k|hd|oled|qled|led|ips|amoled)\b/u', $rest)))) {
            return null;
        }

        if (preg_match('/^(?:სმ|cm|მმ|mm)\b/u', $rest)) {
            return null;
        }

        return static::screenValue((float) $match[1]);
    }

    /**
     * @return array{code: string, ka: string, en: string, sort: int}|null
     */
    protected static function screenValue(float $inches): ?array
    {
        if ($inches < 3 || $inches > 120) {
            return null;
        }

        $number = rtrim(rtrim(number_format(round($inches, 1), 1, '.', ''), '0'), '.');
        $label = $number.'"';

        return ['code' => str_replace('.', '-', $number).'-inch', 'ka' => $label, 'en' => $label, 'sort' => (int) round($inches * 10)];
    }

    /**
     * @return array{code: string, ka: string, en: string, sort: int}|null
     */
    protected static function capacity(string $text): ?array
    {
        if (! preg_match('/^(\d+(?:\.\d+)?)\s?(ლიტრი|ლიტ|ლ|l|liters?|litres?|მლ|ml|კგ|kg)(?![a-z\p{Georgian}])(.*)$/u', $text, $match)) {
            return null;
        }

        if (preg_match(self::AFTER, $match[3]) || preg_match('/\d/u', $match[3])) {
            return null;
        }

        $amount = (float) $match[1];

        if (in_array($match[2], ['კგ', 'kg'], true)) {
            if ($amount < 1 || $amount > 30) {
                return null;
            }

            $number = static::trimmed($amount);

            return ['code' => str_replace('.', '-', $number).'-kg', 'ka' => $number.' კგ', 'en' => $number.' kg', 'sort' => 30000 + (int) round($amount * 10)];
        }

        $millilitres = in_array($match[2], ['მლ', 'ml'], true) ? $amount : $amount * 1000;

        if ($millilitres < 50 || $millilitres > 2000000) {
            return null;
        }

        if ($millilitres < 1000) {
            $number = static::trimmed($millilitres);

            return ['code' => str_replace('.', '-', $number).'-ml', 'ka' => $number.' მლ', 'en' => $number.' ml', 'sort' => (int) round($millilitres / 100)];
        }

        $number = static::trimmed($millilitres / 1000);

        return ['code' => str_replace('.', '-', $number).'-l', 'ka' => $number.' ლ', 'en' => $number.' L', 'sort' => (int) min(29999, round($millilitres / 100))];
    }

    protected static function trimmed(float $amount): string
    {
        return rtrim(rtrim(number_format(round($amount, 2), 2, '.', ''), '0'), '.');
    }
}
