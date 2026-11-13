<?php

namespace App\Filament\Resources\Products\Tables;

use App\Filament\Support\CategoryOptions;
use App\Filament\Support\Operators;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Supplier;
use App\Support\Catalog;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                ImageColumn::make('coverImage.path')
                    ->label('')
                    ->disk('public')
                    ->imageHeight(44)
                    ->square(),
                TextColumn::make('kaTranslation.name')
                    ->label(__('admin.products.name'))
                    ->description(fn (Product $record): string => $record->sku)
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                        ->where('sku', 'like', "%{$search}%")
                        ->orWhere('supplier_sku', 'like', "%{$search}%")
                        ->orWhereHas('translations', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))))
                    ->wrap(),
                TextColumn::make('category.kaTranslation.title')
                    ->label(__('admin.common.category'))
                    ->toggleable(),
                TextColumn::make('brand.name')
                    ->label(__('admin.products.brand'))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('price')
                    ->label(__('admin.products.price'))
                    ->formatStateUsing(fn ($state) => Catalog::money($state))
                    ->description(fn (Product $record): ?string => $record->old_price ? Catalog::money($record->old_price) : null)
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->iconPosition(IconPosition::After)
                    ->iconColor('gray')
                    ->tooltip(__('admin.products.quick_edit'))
                    ->action(static::pricingAction('editPrice', 'price'))
                    ->sortable(),
                TextColumn::make('stock')
                    ->label(__('admin.products.stock'))
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state === 0 => 'danger',
                        $state <= 3 => 'warning',
                        default => 'success',
                    })
                    ->tooltip(fn (): ?string => Operators::active() ? null : __('admin.products.quick_edit'))
                    ->action(Operators::active() ? null : static::pricingAction('editStock', 'stock'))
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label(__('admin.common.is_active'))
                    ->disabled(fn (): bool => Operators::active()),
                TextColumn::make('position')
                    ->label(__('admin.common.position'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('admin.common.updated_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label(__('admin.common.category'))
                    ->options(fn () => CategoryOptions::paths())
                    ->searchable(),
                SelectFilter::make('brand_id')
                    ->label(__('admin.products.brand'))
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('supplier')
                    ->label(__('admin.mapping.supplier'))
                    ->options(fn () => Supplier::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $query, $supplierId) => $query->whereHas('offers', fn (Builder $offers) => $offers->where('supplier_id', $supplierId)))),
                TernaryFilter::make('is_active')
                    ->label(__('admin.common.is_active')),
                Filter::make('on_sale')
                    ->label(__('admin.products.on_sale'))
                    ->query(fn (Builder $query) => $query->whereNotNull('old_price')),
                Filter::make('out_of_stock')
                    ->label(__('admin.products.out_of_stock'))
                    ->query(fn (Builder $query) => $query->where('stock', 0)),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('')
                    ->tooltip(__('admin.common.open_on_site'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (Product $record) => static::siteUrl($record), shouldOpenInNewTab: true)
                    ->visible(fn (Product $record) => $record->is_active),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    static::bulkEditAction()
                        ->hidden(fn (): bool => Operators::active()),
                    BulkAction::make('activate')
                        ->hidden(fn (): bool => Operators::active())
                        ->label(__('admin.common.active'))
                        ->icon(Heroicon::OutlinedEye)
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('deactivate')
                        ->hidden(fn (): bool => Operators::active())
                        ->label(__('admin.common.inactive'))
                        ->icon(Heroicon::OutlinedEyeSlash)
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()
                        ->modalHeading(fn (Collection $records): string => __('admin.products.bulk_delete_heading', ['count' => $records->count()]))
                        ->modalDescription(function (Collection $records): string {
                            $imported = Product::query()->whereKey($records->modelKeys())->has('offers')->count();

                            return $imported
                                ? __('admin.products.bulk_delete_imported', ['count' => $imported])
                                : __('admin.products.bulk_delete_help');
                        }),
                ]),
            ]);
    }

    public static function pricingAction(string $name, string $focus): Action
    {
        return Action::make($name)
            ->modalHeading(fn (Product $record): string => $record->kaTranslation?->name ?? $record->sku)
            ->modalDescription(fn (Product $record): string => __('admin.products.sku').': '.$record->sku)
            ->modalIcon(Heroicon::OutlinedBanknotes)
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel(__('admin.products.quick_save'))
            ->fillForm(fn (Product $record): array => [
                'price' => $record->price,
                'old_price' => $record->old_price,
                'stock' => $record->stock,
                'price_lock' => $record->price_lock,
            ])
            ->schema(fn (Product $record): array => [
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    TextInput::make('price')
                        ->label(__('admin.products.price'))
                        ->numeric()
                        ->minValue(0)
                        ->suffix('₾')
                        ->required()
                        ->autofocus($focus === 'price')
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Set $set, mixed $state) use ($record): void {
                            if (static::isImported($record) && (float) $state !== (float) $record->price) {
                                $set('price_lock', true);
                            }
                        }),
                    TextInput::make('old_price')
                        ->label(__('admin.products.old_price'))
                        ->numeric()
                        ->suffix('₾')
                        ->gt('price'),
                ]),
                TextInput::make('stock')
                    ->label(__('admin.products.stock'))
                    ->hidden(fn (): bool => Operators::active())
                    ->integer()
                    ->minValue(0)
                    ->required()
                    ->autofocus($focus === 'stock')
                    ->helperText(static::isImported($record) ? __('admin.products.stock_from_offers') : null),
                Toggle::make('price_lock')
                    ->label(__('admin.product_import.price_lock'))
                    ->helperText(__('admin.products.price_lock_quick'))
                    ->visible(static::isImported($record)),
            ])
            ->action(function (Product $record, array $data): void {
                $record->update([
                    'price' => $data['price'],
                    'old_price' => filled($data['old_price'] ?? null) ? $data['old_price'] : null,
                    'stock' => Operators::active() ? $record->stock : (int) $data['stock'],
                    'price_lock' => static::isImported($record) ? (bool) ($data['price_lock'] ?? false) : $record->price_lock,
                ]);

                Notification::make()->title(__('admin.products.quick_saved'))->success()->send();
            });
    }

    public static function bulkEditAction(): BulkAction
    {
        return BulkAction::make('bulkEdit')
            ->label(__('admin.products.bulk_edit'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->modalHeading(fn (Collection $records): string => __('admin.products.bulk_edit_heading', ['count' => $records->count()]))
            ->modalDescription(__('admin.products.bulk_edit_help'))
            ->modalIcon(Heroicon::OutlinedPencilSquare)
            ->modalSubmitActionLabel(__('admin.products.bulk_apply'))
            ->schema([
                Select::make('category_id')
                    ->label(__('admin.common.category'))
                    ->options(fn (): array => CategoryOptions::paths())
                    ->searchable()
                    ->placeholder(__('admin.products.bulk_keep'))
                    ->requiredWithout('brand_id')
                    ->validationMessages(['required_without' => __('admin.products.bulk_nothing')]),
                Select::make('brand_id')
                    ->label(__('admin.products.brand'))
                    ->options(fn (): array => Brand::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->placeholder(__('admin.products.bulk_keep'))
                    ->requiredWithout('category_id')
                    ->validationMessages(['required_without' => __('admin.products.bulk_nothing')]),
                Toggle::make('taxonomy_lock')
                    ->label(__('admin.products.bulk_lock'))
                    ->helperText(__('admin.products.bulk_lock_help'))
                    ->default(true),
            ])
            ->action(function (Collection $records, array $data): void {
                $changes = array_filter([
                    'category_id' => $data['category_id'] ?? null,
                    'brand_id' => $data['brand_id'] ?? null,
                ]);

                if ($data['taxonomy_lock'] ?? false) {
                    $changes['taxonomy_lock'] = true;
                }

                $updated = Product::query()->whereKey($records->modelKeys())->update($changes);

                Catalog::flush();

                Notification::make()->title(__('admin.products.bulk_done', ['count' => $updated]))->success()->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    protected static function isImported(Product $product): bool
    {
        return $product->offers()->exists();
    }

    public static function siteUrl(Product $product): ?string
    {
        return $product->siteUrl();
    }
}
