<?php

namespace App\Filament\Support;

class Operators
{
    public static function active(): bool
    {
        return auth()->user()?->isOperator() === true;
    }
}
