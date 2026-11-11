<?php

namespace App\Filament\Resources\Suppliers\RelationManagers;

use App\Filament\Resources\Products\ProductResource;
use App\Models\ProductOffer;
use App\Support\Catalog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
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

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('synced_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product.kaTranslation']))
            ->recordUrl(fn (ProductOffer $record) => $record->product ? ProductResource::getUrl('edit', ['record' => $record->product]) : null)
            ->columns([
                TextColumn::make('external_id')
                    ->label(__('admin.suppliers.external_id'))
                    ->searchable(),
                TextColumn::make('product.kaTranslation.name')
                    ->label(__('admin.products.name'))
                    ->description(fn (ProductOffer $record) => $record->product?->sku)
                    ->wrap(),
                TextColumn::make('external_category')
                    ->label(__('admin.suppliers.external_category'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('cost_price')
                    ->label(__('admin.suppliers.cost_price'))
                    ->formatStateUsing(fn ($state) => Catalog::money($state))
                    ->sortable(),
                TextColumn::make('stock')
                    ->label(__('admin.products.stock'))
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'danger')
                    ->sortable(),
                TextColumn::make('synced_at')
                    ->label(__('admin.suppliers.synced_at'))
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('in_stock')
                    ->label(__('admin.products.in_stock'))
                    ->query(fn (Builder $query) => $query->where('stock', '>', 0)),
            ]);
    }
}
