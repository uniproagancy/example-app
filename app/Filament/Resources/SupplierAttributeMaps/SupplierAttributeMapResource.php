<?php

namespace App\Filament\Resources\SupplierAttributeMaps;

use App\Filament\Resources\SupplierAttributeMaps\Pages\ManageSupplierAttributeMaps;
use App\Filament\Support\AdminOnly;
use App\Filament\Support\AttributeOptions;
use App\Filament\Support\OptionSearch;
use App\Models\SupplierAttributeMap;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class SupplierAttributeMapResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = SupplierAttributeMap::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'mapping/attributes';

    public static function getModelLabel(): string
    {
        return __('admin.mapping.attribute_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.mapping.attributes');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.import');
    }

    public static function hasTitleCaseModelLabel(): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function attributeSelect(): Select
    {
        return Select::make('attribute_id')
            ->label(__('admin.mapping.target_attribute'))
            ->options(fn () => AttributeOptions::all())
            ->searchable()
            ->required();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('hits', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with('supplier')
                ->addSelect(['products_count' => DB::table('product_specs')
                    ->selectRaw('count(distinct product_id)')
                    ->whereColumn('product_specs.attribute_id', 'supplier_attribute_map.attribute_id')]))
            ->columns([
                TextColumn::make('supplier.name')
                    ->label(__('admin.mapping.supplier'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('external_name')
                    ->label(__('admin.mapping.external_name'))
                    ->searchable()
                    ->weight('bold')
                    ->wrap(),
                TextColumn::make('hits')
                    ->label(__('admin.mapping.hits'))
                    ->alignCenter()
                    ->sortable(),
                SelectColumn::make('attribute_id')
                    ->label(__('admin.mapping.target_attribute'))
                    ->placeholder(__('admin.mapping.choose_attribute'))
                    ->native(false)
                    ->searchableOptions()
                    ->options(fn (SupplierAttributeMap $record): array => OptionSearch::suggest(AttributeOptions::all(), $record->external_name))
                    ->getOptionsSearchResultsUsing(fn (?string $search): array => OptionSearch::search(AttributeOptions::all(), $search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => AttributeOptions::all()[(int) $value] ?? null)
                    ->optionsSearchPrompt(__('admin.mapping.search_attribute'))
                    ->noOptionsSearchResultsMessage(__('admin.mapping.no_match'))
                    ->wrapOptionLabels()
                    ->afterStateUpdated(fn (SupplierAttributeMap $record) => Notification::make()
                        ->title(__('admin.mapping.mapped_to', ['from' => $record->external_name, 'to' => AttributeOptions::all()[$record->attribute_id] ?? '—']))
                        ->success()
                        ->send())
                    ->extraAttributes(['class' => 'min-w-72']),
                TextColumn::make('products_count')
                    ->label(__('admin.mapping.products'))
                    ->alignCenter()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('mapped')
                    ->label(__('admin.mapping.state'))
                    ->trueLabel(__('admin.mapping.mapped'))
                    ->falseLabel(__('admin.mapping.unmapped'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('attribute_id'),
                        false: fn (Builder $query) => $query->whereNull('attribute_id'),
                    ),
                SelectFilter::make('supplier_id')
                    ->label(__('admin.mapping.supplier'))
                    ->relationship('supplier', 'name')
                    ->preload(),
            ])
            ->recordActions([
                Action::make('unmap')
                    ->label(__('admin.mapping.unmap'))
                    ->hiddenLabel()
                    ->tooltip(__('admin.mapping.unmap'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('gray')
                    ->visible(fn (SupplierAttributeMap $record) => $record->attribute_id !== null)
                    ->action(fn (SupplierAttributeMap $record) => $record->update(['attribute_id' => null])),
                DeleteAction::make()
                    ->label(__('admin.mapping.forget'))
                    ->hiddenLabel(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('map')
                        ->label(__('admin.mapping.bulk_map'))
                        ->icon(Heroicon::OutlinedLink)
                        ->schema([static::attributeSelect()])
                        ->action(function (Collection $records, array $data): void {
                            $records->each->update(['attribute_id' => (int) $data['attribute_id']]);
                            Notification::make()->title(__('admin.mapping.saved'))->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSupplierAttributeMaps::route('/'),
        ];
    }
}
