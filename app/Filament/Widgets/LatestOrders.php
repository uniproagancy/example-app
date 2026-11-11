<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Support\Catalog;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestOrders extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('admin.dashboard.latest_orders'))
            ->query(Order::query()->latest('id')->limit(8))
            ->paginated(false)
            ->emptyStateHeading(__('admin.dashboard.no_orders'))
            ->emptyStateDescription(__('admin.dashboard.no_orders_help'))
            ->emptyStateIcon(Heroicon::OutlinedShoppingBag)
            ->recordUrl(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('number')
                    ->label(__('admin.orders.number'))
                    ->weight(FontWeight::Bold),
                TextColumn::make('first_name')
                    ->label(__('admin.orders.customer'))
                    ->formatStateUsing(fn (Order $record) => trim($record->first_name.' '.$record->last_name))
                    ->description(fn (Order $record) => $record->phone),
                TextColumn::make('status')
                    ->label(__('admin.common.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => OrderResource::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state) => OrderResource::statusColor($state)),
                TextColumn::make('total')
                    ->label(__('admin.orders.total'))
                    ->formatStateUsing(fn ($state) => Catalog::money($state)),
                TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->since(),
            ]);
    }
}
