<?php

namespace App\Filament\Resources\PriceAlerts\Pages;

use App\Filament\Resources\PriceAlerts\PriceAlertResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePriceAlerts extends ManageRecords
{
    protected static string $resource = PriceAlertResource::class;

    public function getSubheading(): ?string
    {
        return __('admin.price_alerts.help');
    }
}
