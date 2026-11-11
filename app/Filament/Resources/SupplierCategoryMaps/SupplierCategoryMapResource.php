<?php

namespace App\Filament\Resources\SupplierCategoryMaps;

use App\Filament\Resources\SupplierCategoryMaps\Pages\ManageSupplierCategoryMaps;
use App\Filament\Support\CategoryOptions;
use App\Models\Category;
use App\Models\SupplierCategoryMap;
use App\Services\Import\SupplierActions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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

class SupplierCategoryMapResource extends Resource
{
    protected static ?string $model = SupplierCategoryMap::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'mapping/categories';

    public static function getModelLabel(): string
    {
        return __('admin.mapping.category_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.mapping.categories');
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

    public static function getNavigationBadge(): ?string
    {
        $count = SupplierCategoryMap::query()->whereNull('category_id')->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function categorySelect(string $name = 'category_id'): Select
    {
        return Select::make($name)
            ->label(__('admin.mapping.target_category'))
            ->options(fn () => CategoryOptions::paths())
            ->searchable()
            ->required();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('hits', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with('supplier')
                ->addSelect(['products_count' => DB::table('product_offers')
                    ->selectRaw('count(distinct product_id)')
                    ->whereColumn('product_offers.supplier_id', 'supplier_category_map.supplier_id')
                    ->whereColumn('product_offers.external_category', 'supplier_category_map.external_name')]))
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
                TextColumn::make('products_count')
                    ->label(__('admin.mapping.products'))
                    ->alignCenter()
                    ->sortable(),
                SelectColumn::make('category_id')
                    ->label(__('admin.mapping.target_category'))
                    ->placeholder(__('admin.mapping.choose_category'))
                    ->native(false)
                    ->searchableOptions()
                    ->options(fn (SupplierCategoryMap $record): array => CategoryOptions::suggest($record->external_name))
                    ->getOptionsSearchResultsUsing(fn (?string $search): array => CategoryOptions::search($search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => CategoryOptions::paths()[(int) $value] ?? null)
                    ->optionsSearchPrompt(__('admin.mapping.search_category'))
                    ->noOptionsSearchResultsMessage(__('admin.mapping.no_match'))
                    ->wrapOptionLabels()
                    ->afterStateUpdated(fn (SupplierCategoryMap $record) => static::afterMapping($record))
                    ->extraAttributes(['class' => 'min-w-72']),
            ])
            ->filters([
                TernaryFilter::make('mapped')
                    ->label(__('admin.mapping.state'))
                    ->trueLabel(__('admin.mapping.mapped'))
                    ->falseLabel(__('admin.mapping.unmapped'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('category_id'),
                        false: fn (Builder $query) => $query->whereNull('category_id'),
                    )
                    ->default(false),
                SelectFilter::make('supplier_id')
                    ->label(__('admin.mapping.supplier'))
                    ->relationship('supplier', 'name')
                    ->preload(),
            ])
            ->headerActions([
                Action::make('relink')
                    ->label(__('admin.mapping.relink'))
                    ->icon(Heroicon::OutlinedLink)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription(__('admin.mapping.relink_help'))
                    ->action(fn () => Notification::make()
                        ->title(__('admin.mapping.relinked', ['count' => app(SupplierActions::class)->relink()]))
                        ->success()
                        ->send()),
            ])
            ->recordActions([
                Action::make('create')
                    ->label(__('admin.mapping.create_category'))
                    ->hiddenLabel()
                    ->tooltip(__('admin.mapping.create_category'))
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->color('gray')
                    ->fillForm(fn (SupplierCategoryMap $record) => ['title' => $record->external_name])
                    ->schema([
                        TextInput::make('title')->label(__('admin.categories.title'))->required()->maxLength(255),
                        Select::make('parent_id')
                            ->label(__('admin.mapping.parent'))
                            ->options(fn () => CategoryOptions::paths())
                            ->searchable(),
                    ])
                    ->action(function (SupplierCategoryMap $record, array $data): void {
                        $category = static::createCategory($record, $data['title'], $data['parent_id'] ?? null);
                        $moved = app(SupplierActions::class)->relinkMap($record->refresh());

                        Notification::make()
                            ->title(__('admin.mapping.created', ['name' => $category->kaTranslation?->title]))
                            ->body($moved ? __('admin.mapping.moved', ['count' => $moved]) : null)
                            ->success()
                            ->send();
                    }),
                Action::make('unmap')
                    ->label(__('admin.mapping.unmap'))
                    ->hiddenLabel()
                    ->tooltip(__('admin.mapping.unmap'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('gray')
                    ->visible(fn (SupplierCategoryMap $record) => $record->category_id !== null)
                    ->action(fn (SupplierCategoryMap $record) => $record->update(['category_id' => null])),
                DeleteAction::make()
                    ->label(__('admin.mapping.forget'))
                    ->hiddenLabel(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('map')
                        ->label(__('admin.mapping.bulk_map'))
                        ->icon(Heroicon::OutlinedLink)
                        ->schema([static::categorySelect()])
                        ->action(function (Collection $records, array $data): void {
                            $records->each->update(['category_id' => (int) $data['category_id']]);
                            $moved = $records->sum(fn (SupplierCategoryMap $record) => app(SupplierActions::class)->relinkMap($record));

                            Notification::make()
                                ->title(__('admin.mapping.saved'))
                                ->body($moved ? __('admin.mapping.moved', ['count' => $moved]) : null)
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function afterMapping(SupplierCategoryMap $record): void
    {
        $moved = app(SupplierActions::class)->relinkMap($record);
        $category = CategoryOptions::paths()[$record->category_id] ?? null;

        Notification::make()
            ->title($category ? __('admin.mapping.mapped_to', ['from' => $record->external_name, 'to' => $category]) : __('admin.mapping.saved'))
            ->body($moved ? __('admin.mapping.moved', ['count' => $moved]) : null)
            ->success()
            ->send();
    }

    public static function createCategory(SupplierCategoryMap $record, string $title, int|string|null $parentId): Category
    {
        $category = Category::createUnder($parentId ? (int) $parentId : null, $title, attributes: ['created_by_supplier_id' => $record->supplier_id]);

        $record->update(['category_id' => $category->id]);
        CategoryOptions::flush();

        return $category;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSupplierCategoryMaps::route('/'),
        ];
    }
}
