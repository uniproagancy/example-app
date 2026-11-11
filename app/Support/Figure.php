<?php

namespace App\Support;

class Figure
{
    public const DOT = 'dot';

    public const COMMA = 'comma';

    public static function money(mixed $value, ?string $decimal = null): ?float
    {

        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        if (is_numeric($text) && ($decimal === self::DOT || ! preg_match('/[.,]\d{3}$/', $text))) {
            return (float) $text;
        }

        $text = (string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', '', $text);
        $text = (string) preg_replace('/[^\d.,]+/u', '', $text);

        if ($text === '') {
            return null;
        }

        $dot = strrpos($text, '.');
        $comma = strrpos($text, ',');

        if ($dot === false && $comma === false) {
            return (float) $text;
        }

        if ($dot !== false && $comma !== false) {
            return self::split($text, max($dot, $comma));
        }

        $at = $dot !== false ? $dot : $comma;
        $separator = $text[$at];
        $whole = substr($text, 0, $at);
        $part = substr($text, $at + 1);

        $told = match ($decimal) {
            self::DOT => $separator === '.',
            self::COMMA => $separator === ',',
            default => null,
        };

        $grouping = $told === null
            ? substr_count($text, $separator) > 1
                || (strlen($part) === 3 && $whole !== '' && $whole !== '0')
            : ! $told;

        return $grouping
            ? (float) preg_replace('/\D+/', '', $text)
            : self::split($text, $at);
    }

    public static function amount(mixed $value, ?string $decimal = null): float
    {
        return self::money($value, $decimal) ?? 0.0;
    }

    public static function typed(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return $value;
        }

        if (! preg_match('/^[\s\x{00A0}\x{202F}]*-?[\d\s\x{00A0}\x{202F}.,]+₾?[\s\x{00A0}\x{202F}]*$/u', $value)) {
            return $value;
        }

        $read = self::money($value);

        return $read === null
            ? $value
            : rtrim(rtrim(number_format($read, 2, '.', ''), '0'), '.');
    }

    protected static function split(string $text, int $at): float
    {
        $whole = preg_replace('/\D+/', '', substr($text, 0, $at));
        $part = preg_replace('/\D+/', '', substr($text, $at + 1));

        return (float) (($whole === '' ? '0' : $whole).'.'.($part === '' ? '0' : $part));
    }
}
