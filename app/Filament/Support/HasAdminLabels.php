<?php

namespace App\Filament\Support;

use UnitEnum;

trait HasAdminLabels
{
    public static function getModelLabel(): string
    {
        return __('admin.'.static::$labelKey.'.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.'.static::$labelKey.'.plural');
    }

    public static function hasTitleCaseModelLabel(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.'.static::$groupKey);
    }
}
