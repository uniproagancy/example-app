<?php

namespace App\Filament\Support;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Checkbox;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class TrashActions
{
    public static function delete(): DeleteAction
    {
        return DeleteAction::make()
            ->schema([static::forceCheckbox()])
            ->using(fn (Model $record, array $data): ?bool => ($data['force'] ?? false) ? $record->forceDelete() : $record->delete());
    }

    public static function deleteBulk(): DeleteBulkAction
    {
        return DeleteBulkAction::make()
            ->schema([static::forceCheckbox()])
            ->using(fn (Collection $records, array $data) => $records->each(fn (Model $record) => ($data['force'] ?? false) ? $record->forceDelete() : $record->delete()));
    }

    protected static function forceCheckbox(): Checkbox
    {
        return Checkbox::make('force')
            ->label(__('admin.trash.force'))
            ->helperText(__('admin.trash.force_help'));
    }
}
