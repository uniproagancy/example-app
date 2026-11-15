<?php

namespace App\Filament\Resources\Suppliers\Pages;

use App\Filament\Resources\Suppliers\SupplierResource;
use App\Filament\Support\TrashActions;
use Filament\Resources\Pages\EditRecord;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SupplierResource::runAction(),
            SupplierResource::uploadAction(),
            SupplierResource::stockAction(),
            ...TrashActions::header(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return SupplierResource::splitConfig($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return SupplierResource::mergeConfig($data);
    }
}
