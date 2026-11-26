<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\AdminOnlyPage;
use App\Models\OrderItem;
use App\Models\Supplier;
use App\Support\Catalog;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use UnitEnum;

class OrderedProducts extends Page implements HasTable
{
    use AdminOnlyPage;
    use InteractsWithTable;

    public const MANUAL = 'manual';

    public const CONFIRMED = ['confirmed'];

    public const PERIODS = ['today', 'yesterday', '3', '7', '30'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'ordered-products';

    protected string $view = 'filament.pages.ordered-products';

    #[Url(as: 'supplier')]
    public string $supplier = '';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.sales');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.ordered_products.label');
    }

    public function getTitle(): string
    {
        return __('admin.ordered_products.label');
    }

    public function showSupplier(string $supplier): void
    {
        $this->supplier = $supplier;
        $this->resetTable();
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    #[Computed]
    public function tabs(): array
    {
        return [
            ['key' => '', 'label' => __('admin.ordered_products.all')],
            ...Supplier::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Supplier $supplier) => ['key' => (string) $supplier->id, 'label' => $supplier->name])
                ->all(),
            ['key' => self::MANUAL, 'label' => __('admin.ordered_products.manual')],
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->scopedQuery())
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('orders.created_at')->orderByDesc('order_items.id'))
            ->groups([
                Group::make('day_supplier')
                    ->label(__('admin.ordered_products.day_supplier'))
                    ->titlePrefixedWithLabel(false)
                    ->getKeyFromRecordUsing(fn (OrderItem $record): string => Carbon::parse($record->ordered_at)->toDateString().'|'.($record->supplier_name ?? ''))
                    ->getTitleFromRecordUsing(fn (OrderItem $record): string => Carbon::parse($record->ordered_at)->format('d.m.Y').' · '.$this->supplierTitle($record))
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderByRaw('date(orders.created_at) '.$this->direction($direction))->orderBy('supplier_name'))
                    ->scopeQueryByKeyUsing(function (Builder $query, ?string $key): Builder {
                        [$day, $supplier] = array_pad(explode('|', (string) $key, 2), 2, '');

                        return $this->whereSupplierName($query->whereDate('orders.created_at', $day), $supplier);
                    })
                    ->groupQueryUsing(fn ($query) => $query->groupByRaw($this->concatSql($query, 'date(order_items.ordered_at)', "coalesce(order_items.supplier_name, '')")))
                    ->collapsible(),
                Group::make('supplier_only')
                    ->label(__('admin.ordered_products.supplier'))
                    ->getKeyFromRecordUsing(fn (OrderItem $record): string => (string) ($record->supplier_name ?? ''))
                    ->getTitleFromRecordUsing(fn (OrderItem $record): string => $this->supplierTitle($record))
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderBy('supplier_name', $this->direction($direction)))
                    ->scopeQueryByKeyUsing(fn (Builder $query, ?string $key): Builder => $this->whereSupplierName($query, (string) $key))
                    ->groupQueryUsing(fn ($query) => $query->groupByRaw("coalesce(order_items.supplier_name, '')"))
                    ->collapsible(),
                Group::make('ordered_on')
                    ->label(__('admin.ordered_products.day'))
                    ->getKeyFromRecordUsing(fn (OrderItem $record): string => Carbon::parse($record->ordered_at)->toDateString())
                    ->getTitleFromRecordUsing(fn (OrderItem $record): string => Carbon::parse($record->ordered_at)->format('d.m.Y'))
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderBy('orders.created_at', $this->direction($direction)))
                    ->scopeQueryByKeyUsing(fn (Builder $query, string $key): Builder => $query->whereDate('orders.created_at', $key))
                    ->groupQueryUsing(fn ($query) => $query->groupByRaw('date(order_items.ordered_at)'))
                    ->collapsible(),
            ])
            ->defaultGroup('day_supplier', 'desc')
            ->emptyStateHeading(__('admin.ordered_products.empty'))
            ->emptyStateDescription(__('admin.ordered_products.empty_help'))
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->columns([
                ImageColumn::make('product.coverImage.path')
                    ->label('')
                    ->disk('public')
                    ->imageHeight(40)
                    ->square(),
                TextColumn::make('name')
                    ->label(__('admin.ordered_products.product'))
                    ->description(fn (OrderItem $record): string => collect([
                        $record->options ? implode(', ', (array) $record->options) : null,
                        $record->product?->sku,
                    ])->filter()->implode(' · '))
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                        ->where('order_items.name', 'like', "%{$search}%")
                        ->orWhere('orders.number', 'like', "%{$search}%")
                        ->orWhereHas('product', fn (Builder $product) => $product->where('sku', 'like', "%{$search}%"))))
                    ->wrap(),
                TextColumn::make('quantity')
                    ->label(__('admin.ordered_products.quantity'))
                    ->badge()
                    ->alignCenter()
                    ->summarize(Sum::make()->label(__('admin.ordered_products.total_quantity'))),
                TextColumn::make('supplier')
                    ->label(__('admin.ordered_products.supplier'))
                    ->state(fn (OrderItem $record): array => $this->offersOf($record)->pluck('supplier.name')->filter()->unique()->values()->all() ?: [__('admin.ordered_products.manual')])
                    ->badge()
                    ->color(fn (string $state): string => $state === __('admin.ordered_products.manual') ? 'gray' : 'info'),
                TextColumn::make('supplier_code')
                    ->label(__('admin.ordered_products.supplier_code'))
                    ->state(fn (OrderItem $record): array => $this->offersOf($record)->pluck('external_id')->filter()->unique()->values()->all())
                    ->placeholder('—')
                    ->copyable(),
                TextColumn::make('cost')
                    ->label(__('admin.ordered_products.cost'))
                    ->state(fn (OrderItem $record): ?string => ($cost = $this->offersOf($record)->min('cost_price') ?? $record->product?->cost_price) !== null ? Catalog::money((float) $cost) : null)
                    ->placeholder('—'),
                TextColumn::make('unit_price')
                    ->label(__('admin.ordered_products.sold_for'))
                    ->formatStateUsing(fn ($state) => Catalog::money($state)),
                TextColumn::make('order_number')
                    ->label(__('admin.ordered_products.order'))
                    ->description(fn (OrderItem $record): string => OrderResource::statusOptions()[$record->order_status] ?? (string) $record->order_status)
                    ->url(fn (OrderItem $record): string => OrderResource::getUrl('view', ['record' => $record->order_id]))
                    ->color('primary'),
                TextColumn::make('ordered_at')
                    ->label(__('admin.ordered_products.ordered_at'))
                    ->dateTime('d.m.Y H:i'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.common.status'))
                    ->multiple()
                    ->options(OrderResource::statusOptions())
                    ->default(self::CONFIRMED)
                    ->query(fn (Builder $query, array $data) => $query->when($data['values'] ?? [], fn (Builder $query, array $statuses) => $query->whereIn('orders.status', $statuses))),
                SelectFilter::make('payment_status')
                    ->label(__('admin.orders.payment_status'))
                    ->multiple()
                    ->options(OrderResource::paymentStatusOptions())
                    ->query(fn (Builder $query, array $data) => $query->when($data['values'] ?? [], fn (Builder $query, array $statuses) => $query->whereIn('orders.payment_status', $statuses))),
                SelectFilter::make('period')
                    ->label(__('admin.ordered_products.period'))
                    ->options(collect(self::PERIODS)->mapWithKeys(fn (string $period) => [$period => __("admin.ordered_products.periods.{$period}")])->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $query, string $period) => $this->applyPeriod($query, $period))),
                Filter::make('ordered_between')
                    ->schema([
                        DatePicker::make('from')->label(__('admin.ordered_products.from')),
                        DatePicker::make('until')->label(__('admin.ordered_products.until')),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('orders.created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('orders.created_at', '<=', $date))),
            ]);
    }

    protected function scopedQuery(): Builder
    {
        $supplierId = ctype_digit($this->supplier) ? (int) $this->supplier : null;

        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')
            ->select('order_items.*', 'orders.created_at as ordered_at', 'orders.number as order_number', 'orders.status as order_status')
            ->selectRaw($this->supplierNameSql().' as supplier_name')
            ->with(['product' => fn ($query) => $query->withTrashed(), 'product.coverImage', 'product.offers.supplier'])
            ->when($supplierId, fn (Builder $query, int $id) => $query->whereHas('product.offers', fn (Builder $offers) => $offers->where('supplier_id', $id)))
            ->when($this->supplier === self::MANUAL, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereNull('order_items.product_id')
                ->orWhereDoesntHave('product.offers')));
    }

    protected function supplierNameSql(): string
    {
        $only = ctype_digit($this->supplier) ? ' and product_offers.supplier_id = '.(int) $this->supplier : '';

        return '(select suppliers.name from product_offers inner join suppliers on suppliers.id = product_offers.supplier_id'
            .' where product_offers.product_id = order_items.product_id'.$only
            .' order by product_offers.cost_price asc, product_offers.id asc limit 1)';
    }

    protected function whereSupplierName(Builder $query, string $name): Builder
    {
        return $query->whereRaw('coalesce('.$this->supplierNameSql().", '') = ?", [$name]);
    }

    protected function supplierTitle(OrderItem $record): string
    {
        return filled($record->supplier_name) ? (string) $record->supplier_name : __('admin.ordered_products.manual');
    }

    protected function concatSql($query, string ...$parts): string
    {
        return $query->getConnection()->getDriverName() === 'sqlite'
            ? implode(" || '|' || ", $parts)
            : 'concat('.implode(", '|', ", $parts).')';
    }

    protected function direction(string $direction): string
    {
        return strtolower($direction) === 'asc' ? 'asc' : 'desc';
    }

    protected function offersOf(OrderItem $record)
    {
        $offers = $record->product?->offers ?? collect();

        return ctype_digit($this->supplier) ? $offers->where('supplier_id', (int) $this->supplier)->values() : $offers;
    }

    protected function applyPeriod(Builder $query, string $period): Builder
    {
        return match ($period) {
            'today' => $query->where('orders.created_at', '>=', today()),
            'yesterday' => $query->whereBetween('orders.created_at', [today()->subDay(), today()->subSecond()]),
            '3', '7', '30' => $query->where('orders.created_at', '>=', Carbon::now()->subDays((int) $period)),
            default => $query,
        };
    }
}
