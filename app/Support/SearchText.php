<?php

namespace App\Support;

use Closure;

final class SearchText
{
    private const GEORGIAN = [
        'ა' => 'a', 'ბ' => 'b', 'გ' => 'g', 'დ' => 'd', 'ე' => 'e', 'ვ' => 'v', 'ზ' => 'z', 'თ' => 't', 'ი' => 'i',
        'კ' => 'k', 'ლ' => 'l', 'მ' => 'm', 'ნ' => 'n', 'ო' => 'o', 'პ' => 'p', 'ჟ' => 'zh', 'რ' => 'r', 'ს' => 's',
        'ტ' => 't', 'უ' => 'u', 'ფ' => 'p', 'ქ' => 'k', 'ღ' => 'gh', 'ყ' => 'k', 'შ' => 'sh', 'ჩ' => 'ch', 'ც' => 'ts',
        'ძ' => 'dz', 'წ' => 'ts', 'ჭ' => 'ch', 'ხ' => 'kh', 'ჯ' => 'j', 'ჰ' => 'h',
    ];

    private const LATIN = [
        'ch' => 'ch', 'ph' => 'p', 'th' => 't', 'ck' => 'k', 'q' => 'k', 'w' => 'v', 'y' => 'i', 'x' => 'kh', 'f' => 'p', 'c' => 'k',
    ];

    public static function key(?string $text): string
    {
        $text = strtr(mb_strtolower((string) $text), self::GEORGIAN);
        $text = strtr($text, self::LATIN);

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $text));
    }

    /**
     * @return list<string>
     */
    public static function words(?string $text): array
    {
        return array_values(array_filter(explode(' ', self::key($text)), fn (string $word) => $word !== ''));
    }

    /**
     * @return Closure(string): int
     */
    public static function scorer(string $query): Closure
    {
        $term = self::key($query);

        if ($term === '') {
            return fn (string $haystack): int => 0;
        }

        $alternatives = array_map(fn (string $word) => array_map(self::stem(...), self::alternatives($word)), explode(' ', $term));

        return function (string $haystack) use ($term, $alternatives): int {
            if ($haystack === '') {
                return 0;
            }

            if (str_starts_with($haystack, $term) || str_contains($haystack, ' '.$term)) {
                return 3;
            }

            if (str_contains($haystack, $term)) {
                return 2;
            }

            foreach ($alternatives as $options) {
                $found = false;

                foreach ($options as $option) {
                    if (str_contains($haystack, $option)) {
                        $found = true;
                        break;
                    }
                }

                if (! $found) {
                    return 0;
                }
            }

            return 1;
        };
    }

    public static function stem(string $word): string
    {
        $length = strlen($word);

        if ($length >= 6) {
            return substr($word, 0, max(4, $length - 3));
        }

        return $length >= 4 ? substr($word, 0, -1) : $word;
    }

    /**
     * @return list<string>
     */
    public static function alternatives(string $word): array
    {
        $found = [$word];

        if (strlen($word) < 2) {
            return $found;
        }

        foreach (self::synonymGroups() as $group) {
            foreach ($group as $member) {
                if ($member === $word || (strlen($word) >= 3 && str_starts_with($member, $word)) || (strlen($member) >= 3 && str_starts_with($word, $member))) {
                    array_push($found, ...$group);
                    break;
                }
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * @return list<list<string>>
     */
    private static function synonymGroups(): array
    {
        return once(fn () => array_values(array_filter(array_map(
            fn (array $group) => array_values(array_unique(array_filter(array_map(fn (string $member) => self::key($member), $group)))),
            (array) config('search.synonyms', []),
        ))));
    }
}
