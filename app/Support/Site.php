<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;

class Site
{
    public const SOCIAL_NETWORKS = ['facebook', 'instagram', 'tiktok', 'youtube', 'linkedin'];

    public static function name(): string
    {
        return Setting::get('site_name') ?: config('app.name');
    }

    public static function logoUrl(): string
    {
        $path = Setting::get('logo');

        if (filled($path) && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        return Vite::asset('resources/images/elio-mark.png');
    }

    public static function hasCustomLogo(): bool
    {
        return filled(Setting::get('logo')) && Storage::disk('public')->exists((string) Setting::get('logo'));
    }

    public static function logoIncludesName(): bool
    {
        return static::hasCustomLogo() && (bool) Setting::get('logo_with_name');
    }

    public static function faviconUrl(): string
    {
        return static::logoIncludesName() ? Vite::asset('resources/images/elio-mark.png') : static::logoUrl();
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
