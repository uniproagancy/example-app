<?php

namespace App\Filament\Support;

use App\Models\Attribute;

class AttributeOptions
{
    protected static ?array $memo = null;

    public static function all(): array
    {
        return static::$memo ??= Attribute::query()
            ->with('kaTranslation')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (Attribute $attribute) => [$attribute->id => ($attribute->kaTranslation?->name ?: $attribute->code).' · '.$attribute->code])
            ->all();
    }

    public static function flush(): void
    {
        static::$memo = null;
    }
}
