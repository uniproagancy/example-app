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

    public static function messenger(): ?string
    {
        $own = trim((string) Setting::get('messenger'));

        if ($own !== '') {
            return str_starts_with($own, 'https://') ? $own : null;
        }

        $page = static::socials()['facebook'] ?? null;

        if (! $page) {
            return null;
        }

        $parts = parse_url($page);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($host, ['facebook.com', 'www.facebook.com', 'm.facebook.com', 'fb.com', 'www.fb.com'], true)) {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        $path = trim((string) ($parts['path'] ?? ''), '/');

        $name = match (true) {
            $path === 'profile.php' => (string) ($query['id'] ?? ''),
            (bool) preg_match('~^people/[^/]+/(\d+)$~', $path, $people) => $people[1],
            default => $path,
        };

        return preg_match('/^[A-Za-z0-9.\-]{2,100}$/', $name) ? 'https://m.me/'.$name : null;
    }

    public static function hours(): array
    {
        return [
            'week' => Setting::get('hours_week'),
            'sunday' => Setting::get('hours_sunday'),
        ];
    }
}
