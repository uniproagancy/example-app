<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Http\Controllers\InvoiceController;
use App\Models\Invoice;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInvoices extends ManageRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth('5xl')
                ->mutateDataUsing(fn (array $data): array => [...$data, 'user_id' => auth()->id()])
                ->after(fn (Invoice $record) => $this->js('window.open('.json_encode(InvoiceController::manualUrl($record)).', "_blank")')),
        ];
    }
}
