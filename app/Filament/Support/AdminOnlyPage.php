<?php

namespace App\Filament\Support;

trait AdminOnlyPage
{
    public static function canAccess(): bool
    {
        return ! Operators::active() && parent::canAccess();
    }
}
