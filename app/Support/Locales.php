<?php

namespace App\Support;

class Locales
{
    public static function default(): string
    {
        return (string) config('app.locale', 'ka');
    }

    public static function codes(): array
    {
        return array_keys((array) config('laravellocalization.supportedLocales', [self::default() => []]));
    }
}
