<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Filament\Support\AdminOnlyPage;
use App\Models\Product;
use App\Models\Supplier;
use App\Support\Catalog;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use UnitEnum;

class SupplierProducts extends Page implements HasTable
{
    use AdminOnlyPage;
    use InteractsWithTable;

    public const MANUAL = 'manual';

    public const PERIODS = ['today', 'yesterday', '3', '7', '30'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'supplier-products';

    protected string $view = 'filament.pages.supplier-products';

    #[Url(as: 'supplier')]
    public string $supplier = '';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.catalog');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.supplier_products.label');
    }

    public function getTitle(): string
    {
        return __('admin.supplier_products.label');
    }

    public function showSupplier(string $supplier): void
    {
        $this->supplier = $supplier;
        $this->resetTable();
    }

    /**
     * @return list<array{key: string, label: string, count: int}>
     */
    #[Computed]
    public function tabs(): array
    {
        $counts = Product::query()
            ->join('product_offers', 'product_offers.product_id', '=', 'products.id')
            ->groupBy('product_offers.supplier_id')
            ->selectRaw('product_offers.supplier_id, count(distinct products.id) as total')
            ->pluck('total', 'product_offers.supplier_id');

        return [
            ['key' => '', 'label' => __('admin.supplier_products.all'), 'count' => Product::query()->count()],
            ...Supplier::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Supplier $supplier) => ['key' => (string) $supplier->id, 'label' => $supplier->name, 'count' => (int) ($counts[$supplier->id] ?? 0)])
                ->all(),
            ['key' => self::MANUAL, 'label' => __('admin.supplier_products.manual'), 'count' => Product::query()->doesntHave('offers')->count()],
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->scopedQuery())
            ->defaultSort('created_at', 'desc')
            ->groups([
                Group::make('created_at')
                    ->label(__('admin.supplier_products.added'))
                    ->date()
                    ->getTitleFromRecordUsing(fn (Product $record): string => $record->created_at?->format('d.m.Y') ?? '—')
                    ->collapsible(),
            ])
            ->defaultGroup('created_at', 'desc')
            ->groupingSettingsHidden()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->recordUrl(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record]))
            ->columns([
                ImageColumn::make('coverImage.path')
                    ->label('')
                    ->disk('public')
                    ->imageHeight(40)
                    ->square(),
                TextColumn::make('kaTranslation.name')
                    ->label(__('admin.products.name'))
                    ->description(fn (Product $record): string => $record->sku)
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                        ->where('sku', 'like', "%{$search}%")
                        ->orWhere('supplier_sku', 'like', "%{$search}%")
                        ->orWhereHas('translations', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))))
                    ->wrap(),
                TextColumn::make('suppliers')
                    ->label(__('admin.supplier_products.supplier'))
                    ->state(fn (Product $record): array => $record->offers->pluck('supplier.name')->filter()->unique()->values()->all() ?: [__('admin.supplier_products.manual')])
                    ->badge()
                    ->color(fn (string $state): string => $state === __('admin.supplier_products.manual') ? 'gray' : 'info'),
                TextColumn::make('category.kaTranslation.title')
                    ->label(__('admin.common.category'))
                    ->placeholder(__('admin.supplier_products.unmapped'))
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('price')
                    ->label(__('admin.products.price'))
                    ->formatStateUsing(fn ($state) => Catalog::money($state))
                    ->description(fn (Product $record): ?string => $record->old_price ? Catalog::money($record->old_price) : null)
                    ->sortable(),
                TextColumn::make('stock')
                    ->label(__('admin.products.stock'))
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state === 0 => 'danger',
                        $state <= 3 => 'warning',
                        default => 'success',
                    })
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label(__('admin.common.is_active')),
                TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('period')
                    ->label(__('admin.supplier_products.period'))
                    ->options(collect(self::PERIODS)->mapWithKeys(fn (string $period) => [$period => __("admin.supplier_products.periods.{$period}")])->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $query, string $period) => $this->applyPeriod($query, $period))),
                Filter::make('created_between')
                    ->schema([
                        DatePicker::make('from')->label(__('admin.supplier_products.from')),
                        DatePicker::make('until')->label(__('admin.supplier_products.until')),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('products.created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('products.created_at', '<=', $date))),
                TernaryFilter::make('is_active')
                    ->label(__('admin.common.is_active')),
                TernaryFilter::make('in_stock')
                    ->label(__('admin.supplier_products.in_stock'))
                    ->queries(
                        true: fn (Builder $query) => $query->where('stock', '>', 0),
                        false: fn (Builder $query) => $query->where('stock', 0),
                    ),
                TernaryFilter::make('has_category')
                    ->label(__('admin.supplier_products.has_category'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('category_id'),
                        false: fn (Builder $query) => $query->whereNull('category_id'),
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ProductsTable::bulkEditAction(),
                    BulkAction::make('activate')
                        ->label(__('admin.common.active'))
                        ->icon(Heroicon::OutlinedEye)
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('deactivate')
                        ->label(__('admin.common.inactive'))
                        ->icon(Heroicon::OutlinedEyeSlash)
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    protected function scopedQuery(): Builder
    {
        return Product::query()
            ->with(['kaTranslation', 'coverImage', 'category.kaTranslation', 'offers.supplier'])
            ->when($this->supplier === self::MANUAL, fn (Builder $query) => $query->doesntHave('offers'))
            ->when(ctype_digit($this->supplier), fn (Builder $query) => $query->whereHas('offers', fn (Builder $offers) => $offers->where('supplier_id', (int) $this->supplier)));
    }

    protected function applyPeriod(Builder $query, string $period): Builder
    {
        return match ($period) {
            'today' => $query->where('products.created_at', '>=', today()),
            'yesterday' => $query->whereBetween('products.created_at', [today()->subDay(), today()->subSecond()]),
            '3', '7', '30' => $query->where('products.created_at', '>=', Carbon::now()->subDays((int) $period)),
            default => $query,
        };
    }
}
