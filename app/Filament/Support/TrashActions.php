<?php

namespace App\Filament\Support;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Checkbox;
use Filament\Tables\Filters\TrashedFilter;
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

    public static function record(?DeleteAction $delete = null, bool $iconOnly = false, ?ForceDeleteAction $forceDelete = null): array
    {
        $actions = [$delete ?? static::delete(), RestoreAction::make(), $forceDelete ?? ForceDeleteAction::make()];

        return $iconOnly ? array_map(fn ($action) => $action->hiddenLabel()->tooltip($action->getLabel()), $actions) : $actions;
    }

    public static function bulk(?DeleteBulkAction $delete = null, bool $withForce = true): array
    {
        if (! $withForce) {
            return [$delete ?? DeleteBulkAction::make(), RestoreBulkAction::make()];
        }

        return [$delete ?? static::deleteBulk(), RestoreBulkAction::make(), ForceDeleteBulkAction::make()];
    }

    public static function header(?DeleteAction $delete = null, ?string $redirectUrl = null): array
    {
        $actions = [$delete ?? static::delete(), RestoreAction::make(), ForceDeleteAction::make()];

        if ($redirectUrl !== null) {
            $actions[0]->successRedirectUrl($redirectUrl);
            $actions[2]->successRedirectUrl($redirectUrl);
        }

        return $actions;
    }

    public static function filter(): TrashedFilter
    {
        return TrashedFilter::make();
    }

    public static function forceCheckbox(): Checkbox
    {
        return Checkbox::make('force')
            ->label(__('admin.trash.force'))
            ->helperText(__('admin.trash.force_help'));
    }
}
