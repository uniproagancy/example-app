<?php

namespace App\Filament\Resources\PriceAlerts;

use App\Filament\Resources\PriceAlerts\Pages\ManagePriceAlerts;
use App\Filament\Support\HasAdminLabels;
use App\Models\PriceAlert;
use App\Support\Catalog;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PriceAlertResource extends Resource
{
    use HasAdminLabels;

    protected static string $labelKey = 'price_alerts';

    protected static string $groupKey = 'sales';

    protected static ?string $model = PriceAlert::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('product_id')
                    ->label(__('admin.price_alerts.product'))
                    ->state(fn (PriceAlert $record) => Catalog::find($record->product_id)['name'] ?? '#'.$record->product_id)
                    ->url(fn (PriceAlert $record) => Catalog::find($record->product_id)['url'] ?? null, shouldOpenInNewTab: true)
                    ->wrap(),
                TextColumn::make('contact')
                    ->label(__('admin.price_alerts.contact'))
                    ->description(fn (PriceAlert $record) => __("admin.price_alerts.channels.{$record->channel}"))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('price')
                    ->label(__('admin.price_alerts.price'))
                    ->formatStateUsing(fn ($state) => Catalog::money($state)),
                TextColumn::make('current')
                    ->label(__('admin.price_alerts.current'))
                    ->state(fn (PriceAlert $record) => Catalog::find($record->product_id)['price'] ?? null)
                    ->formatStateUsing(fn ($state) => Catalog::money($state))
                    ->color(fn (PriceAlert $record, $state) => $state !== null && (float) $state < (float) $record->price ? 'success' : null),
                TextColumn::make('notified_at')
                    ->label(__('admin.price_alerts.notified'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder(__('admin.price_alerts.waiting'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('notified')
                    ->label(__('admin.price_alerts.notified'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('notified_at'),
                        false: fn (Builder $query) => $query->whereNull('notified_at'),
                    ),
            ])
            ->recordActions([
                DeleteAction::make()->hiddenLabel(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePriceAlerts::route('/'),
        ];
    }
}
