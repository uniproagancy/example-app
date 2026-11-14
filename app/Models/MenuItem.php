<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

#[Fillable(['location', 'title', 'route_name', 'url', 'position', 'is_active'])]
class MenuItem extends Model
{
    use LogsActivity;

    public const CACHE_KEY = 'menu_items.all';

    public const LOCATIONS = ['header', 'header_highlight'];

    public const ROUTES = ['home', 'catalog', 'categories', 'promotions', 'about', 'contact', 'checkout'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public static function for(string $location): Collection
    {
        $rows = Cache::rememberForever(
            self::CACHE_KEY,
            fn () => static::query()->active()->orderBy('position')->toBase()->get()->map(fn ($row) => (array) $row)->all(),
        );

        return static::hydrate($rows)->where('location', $location)->values();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    protected function label(): Attribute
    {
        return Attribute::get(function (): string {
            $titles = $this->title ?? [];

            return $titles[app()->getLocale()]
                ?? $titles[config('app.fallback_locale')]
                ?? (string) reset($titles);
        });
    }

    protected function href(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->route_name && Route::has($this->route_name)) {
                return route($this->route_name).($this->url ?? '');
            }

            if ($this->url && str_starts_with($this->url, '/')) {
                return LaravelLocalization::localizeUrl($this->url);
            }

            return $this->url ?: '#';
        });
    }

    public function isCurrent(): bool
    {
        return $this->route_name !== null
            && blank($this->url)
            && request()->routeIs($this->route_name);
    }

    public function isPageLink(): bool
    {
        return ! str_contains($this->href, '#');
    }
}
