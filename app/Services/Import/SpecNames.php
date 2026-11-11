<?php

namespace App\Services\Import;

class SpecNames
{
    public static function isGeorgian(string $name): bool
    {
        return (bool) preg_match('/[\x{10A0}-\x{10FF}\x{1C90}-\x{1CBF}]/u', $name);
    }

    public static function translate(string $name): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8')), " \t:：");

        if ($name === '' || static::isGeorgian($name)) {
            return ['ka' => $name, 'en' => null];
        }

        preg_match('/^(?<base>[^(\[]+?)\s*(?<rest>[(\[].*)?$/u', $name, $parts);

        $base = mb_strtolower(trim($parts['base'] ?? $name));
        $georgian = config('import.spec_names', [])[$base] ?? null;

        if (! $georgian) {
            return ['ka' => $name, 'en' => $name];
        }

        return [
            'ka' => trim($georgian.' '.($parts['rest'] ?? '')),
            'en' => $name,
        ];
    }
}
