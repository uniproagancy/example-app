<?php

namespace App\Filament\Resources\AbandonedCarts\Pages;

use App\Filament\Resources\AbandonedCarts\AbandonedCartResource;
use Filament\Resources\Pages\ManageRecords;

class ManageAbandonedCarts extends ManageRecords
{
    protected static string $resource = AbandonedCartResource::class;

    public function getSubheading(): ?string
    {
        return __('admin.abandoned_carts.help');
    }
}
