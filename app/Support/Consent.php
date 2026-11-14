<?php

namespace App\Support;

use App\Models\Setting;
use App\Services\Facebook\Pixel;

class Consent
{
    public const COOKIE = 'cookie_consent';

    public const GRANTED = 'granted';

    public const DENIED = 'denied';

    public static function choice(): ?string
    {
        $value = request()->cookie(self::COOKIE);

        return in_array($value, [self::GRANTED, self::DENIED], true) ? $value : null;
    }

    public static function denied(): bool
    {
        return static::choice() === self::DENIED;
    }

    public static function ga4Id(): ?string
    {
        $id = trim((string) Setting::get('ga4_id'));

        return preg_match('/^G-[A-Z0-9]{4,20}$/', $id) ? $id : null;
    }

    public static function tracksVisitors(): bool
    {
        return static::ga4Id() !== null || app(Pixel::class)->pixelId() !== null;
    }
}
