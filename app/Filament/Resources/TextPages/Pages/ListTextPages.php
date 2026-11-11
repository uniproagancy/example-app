<?php

namespace App\Filament\Resources\TextPages\Pages;

use App\Filament\Resources\TextPages\TextPageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTextPages extends ListRecords
{
    protected static string $resource = TextPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
