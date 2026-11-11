<?php

namespace App\Services\Import\Drivers\Alta;

class AltaPaste
{
    public static function split(string $pasted): array
    {
        $pasted = trim($pasted);

        if ($pasted === '') {
            return [];
        }

        $whole = json_decode($pasted, true);

        if (is_array($whole) && array_is_list($whole) && $whole !== []) {
            return array_map(
                fn ($one) => is_string($one) ? $one : json_encode($one, JSON_UNESCAPED_UNICODE),
                $whole,
            );
        }

        if (is_array($whole)) {
            return [$pasted];
        }

        return self::objects($pasted);
    }

    protected static function objects(string $text): array
    {
        $found = [];
        $depth = 0;
        $start = null;
        $inString = false;
        $escaped = false;

        $length = strlen($text);

        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;

                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;

                continue;
            }

            if ($char === '{') {
                if ($depth === 0) {
                    $start = $i;
                }

                $depth++;

                continue;
            }

            if ($char === '}') {
                $depth--;

                if ($depth === 0 && $start !== null) {
                    $found[] = substr($text, $start, $i - $start + 1);
                    $start = null;
                }

                if ($depth < 0) {

                    $depth = 0;
                }
            }
        }

        return $found ?: [$text];
    }
}
