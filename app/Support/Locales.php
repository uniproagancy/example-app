<?php

namespace App\Support;

use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class Locales
{
    public static function default(): string
    {
        return (string) (LaravelLocalization::getDefaultLocale() ?: config('app.locale', 'ka'));
    }

    public static function codes(): array
    {
        return array_keys((array) config('laravellocalization.supportedLocales', [self::default() => []]));
    }
}
