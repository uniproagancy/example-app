<?php

namespace App\Filament\Support;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

trait AdminOnly
{
    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        return Operators::active() ? Response::deny() : parent::getAuthorizationResponse($action, $record);
    }
}
