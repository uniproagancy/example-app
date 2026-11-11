<?php

namespace App\Filament\Resources\Perks\Pages;

use App\Filament\Resources\Perks\PerkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePerks extends ManageRecords
{
    protected static string $resource = PerkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
