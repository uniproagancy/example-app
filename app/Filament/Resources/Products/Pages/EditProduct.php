<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Support\Catalog;
use App\Support\VariantGroups;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')
                ->label(__('admin.common.open_on_site'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn () => ProductsTable::siteUrl($this->getRecord()), shouldOpenInNewTab: true)
                ->visible(fn () => $this->getRecord()->is_active),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        VariantGroups::link($this->getRecord(), (array) ($this->data['variant_siblings'] ?? []));
        Catalog::flush();
    }
}
