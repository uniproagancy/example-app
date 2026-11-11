<?php

namespace App\Filament\Support;

use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

class Schedule
{
    public static function fields(): Fieldset
    {
        return Fieldset::make(__('admin.common.schedule'))
            ->columns(2)
            ->schema([
                DateTimePicker::make('starts_at')
                    ->label(__('admin.common.starts_at'))
                    ->seconds(false),
                DateTimePicker::make('ends_at')
                    ->label(__('admin.common.ends_at'))
                    ->seconds(false)
                    ->after('starts_at')
                    ->helperText(__('admin.common.schedule_help')),
            ])
            ->columnSpanFull();
    }

    public static function state(Model $record): string
    {
        return match (true) {
            ! $record->is_active => 'inactive',
            $record->starts_at && $record->starts_at->isFuture() => 'scheduled',
            $record->ends_at && $record->ends_at->isPast() => 'expired',
            default => 'live',
        };
    }

    public static function column(): TextColumn
    {
        return TextColumn::make('schedule_state')
            ->label(__('admin.common.status'))
            ->state(fn (Model $record) => static::state($record))
            ->badge()
            ->formatStateUsing(fn (string $state) => $state === 'inactive' ? __('admin.common.inactive') : __("admin.common.$state"))
            ->color(fn (string $state) => match ($state) {
                'live' => 'success',
                'scheduled' => 'info',
                'expired' => 'danger',
                default => 'gray',
            })
            ->description(fn (Model $record) => collect([$record->starts_at?->format('d.m.Y'), $record->ends_at?->format('d.m.Y')])->filter()->implode(' – ') ?: null);
    }
}
