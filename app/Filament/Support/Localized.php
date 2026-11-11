<?php

namespace App\Filament\Support;

use Closure;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

class Localized
{
    public const PRIMARY = 'ka';

    public static function locales(): array
    {
        return collect(array_keys(config('laravellocalization.supportedLocales')))
            ->mapWithKeys(fn (string $locale) => [$locale => __("admin.locales.$locale")])
            ->all();
    }

    public static function tabs(string $key, Closure $fields): Tabs
    {
        return Tabs::make()
            ->key($key)
            ->tabs(
                collect(static::locales())
                    ->map(fn (string $label, string $locale) => Tab::make($label)
                        ->key("$key-$locale")
                        ->schema($fields($locale, $locale === static::PRIMARY)))
                    ->values()
                    ->all(),
            )
            ->columnSpanFull();
    }

    public static function relationTabs(string $key, string $requiredField, Closure $fields): Tabs
    {
        return static::tabs($key, fn (string $locale, bool $isPrimary) => [
            Group::make()
                ->relationship(
                    "{$locale}Translation",
                    condition: $isPrimary ? true : fn (?array $state): bool => filled($state[$requiredField] ?? null),
                )
                ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => [...$data, 'locale' => $locale])
                ->schema($fields($locale, $isPrimary)),
        ]);
    }

    public static function value(?array $values): ?string
    {
        if (! $values) {
            return null;
        }

        return $values[static::PRIMARY] ?? (string) reset($values);
    }

    public static function options(string $prefix, array $keys): array
    {
        return collect($keys)->mapWithKeys(fn (string|int $key) => [$key => __("$prefix.$key")])->all();
    }
}
