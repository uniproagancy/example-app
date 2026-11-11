<?php

namespace App\Support;

use App\Models\Setting;

class Site
{
    public const SOCIAL_NETWORKS = ['facebook', 'instagram', 'tiktok', 'youtube', 'linkedin'];

    public static function name(): string
    {
        return Setting::get('site_name') ?: config('app.name');
    }

    public static function nameParts(): array
    {
        $name = static::name();
        $dot = strrpos($name, '.');

        if ($dot === false || $dot === 0) {
            return [$name, ''];
        }

        return [substr($name, 0, $dot), substr($name, $dot)];
    }

    public static function phone(): ?string
    {
        return Setting::get('phone');
    }

    public static function phoneHref(): string
    {
        return 'tel:'.preg_replace('/[^\d+*#]/', '', (string) static::phone());
    }

    public static function hotline(): ?string
    {
        return Setting::get('hotline');
    }

    public static function email(): ?string
    {
        return Setting::get('email');
    }

    public static function socials(): array
    {
        return collect(self::SOCIAL_NETWORKS)
            ->mapWithKeys(fn (string $network) => [$network => Setting::get($network)])
            ->filter(fn (?string $url) => filled($url) && str_starts_with($url, 'https://'))
            ->all();
    }

    public static function hours(): array
    {
        return [
            'week' => Setting::get('hours_week'),
            'sunday' => Setting::get('hours_sunday'),
        ];
    }
}
