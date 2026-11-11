<?php

namespace App\Filament\Resources\TextPages\Pages;

use App\Filament\Resources\TextPages\TextPageResource;
use App\Models\Page;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;

class EditTextPage extends EditRecord
{
    protected static string $resource = TextPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')
                ->label(__('admin.common.open_on_site'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (): string => url('ka/info/'.$this->getRecord()->slug), shouldOpenInNewTab: true),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        Cache::forget(Page::CACHE_KEY);
    }
}
