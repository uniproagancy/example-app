<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\ProductOffer;
use App\Support\Catalog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OffersRelationManager extends RelationManager
{
    protected static string $relationship = 'offers';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.product_import.offers');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->offers()->exists();
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('cost_price')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('supplier'))
            ->paginated(false)
            ->columns([
                TextColumn::make('supplier.name')
                    ->label(__('admin.mapping.supplier'))
                    ->description(fn (ProductOffer $record) => $record->external_id)
                    ->weight('bold'),
                TextColumn::make('cost_price')
                    ->label(__('admin.suppliers.cost_price'))
                    ->formatStateUsing(fn ($state) => Catalog::money($state))
                    ->description(fn (ProductOffer $record) => $record->old_cost_price ? Catalog::money($record->old_cost_price) : null),
                TextColumn::make('markup_price')
                    ->label(__('admin.products.price'))
                    ->state(fn (ProductOffer $record) => $record->supplier ? Catalog::money($record->supplier->markup((float) $record->cost_price)) : null),
                TextColumn::make('stock')
                    ->label(__('admin.products.stock'))
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'danger'),
                TextColumn::make('external_category')
                    ->label(__('admin.suppliers.external_category'))
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('synced_at')
                    ->label(__('admin.suppliers.synced_at'))
                    ->since(),
            ]);
    }
}
