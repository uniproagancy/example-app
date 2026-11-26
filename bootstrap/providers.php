<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\CourierPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    CourierPanelProvider::class,
];
