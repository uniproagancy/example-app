<?php

namespace App\Filament\Resources\Invoices;

use App\Filament\Resources\Invoices\Pages\ManageInvoices;
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Support\HasAdminLabels;
use App\Filament\Support\HasTrash;
use App\Filament\Support\TrashActions;
use App\Http\Controllers\InvoiceController;
use App\Models\Invoice;
use App\Support\Catalog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvoiceResource extends Resource
{
    use HasAdminLabels;
    use HasTrash;

    protected static string $labelKey = 'invoices';

    protected static string $groupKey = 'sales';

    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make(__('invoice.buyer'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('buyer_name')
                            ->label(__('admin.invoices.buyer_name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label(__('checkout.phone'))
                            ->tel()
                            ->maxLength(32),
                        TextInput::make('buyer_company')
                            ->label(__('admin.invoices.buyer_company'))
                            ->maxLength(255),
                        TextInput::make('buyer_code')
                            ->label(__('admin.invoices.buyer_code'))
                            ->maxLength(32),
                        TextInput::make('email')
                            ->label(__('checkout.email'))
                            ->email()
                            ->maxLength(255),
                        TextInput::make('address')
                            ->label(__('invoice.address'))
                            ->maxLength(255),
                    ]),
                Section::make(__('admin.invoices.lines'))
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('lines')
                            ->hiddenLabel()
                            ->addActionLabel(__('admin.invoices.add_line'))
                            ->minItems(1)
                            ->defaultItems(1)
                            ->columns(12)
                            ->live(debounce: 500)
                            ->schema([
                                Select::make('product_id')
                                    ->label(__('admin.invoices.product'))
                                    ->helperText(__('admin.invoices.product_help'))
                                    ->searchable()
                                    ->getSearchResultsUsing(fn (string $search): array => CreateOrder::searchProducts($search))
                                    ->getOptionLabelUsing(fn ($value): ?string => CreateOrder::productLabel((int) $value))
                                    ->live()
                                    ->afterStateUpdated(fn ($state, Set $set) => static::fillFromProduct($state, $set))
                                    ->columnSpan(['default' => 12, 'md' => 5]),
                                TextInput::make('name')
                                    ->label(__('invoice.product'))
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(['default' => 12, 'md' => 4]),
                                TextInput::make('quantity')
                                    ->label(__('invoice.quantity'))
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(100000)
                                    ->default(1)
                                    ->required()
                                    ->columnSpan(['default' => 4, 'md' => 1]),
                                TextInput::make('unit_price')
                                    ->label(__('invoice.price'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix('₾')
                                    ->required()
                                    ->columnSpan(['default' => 8, 'md' => 2]),
                            ]),
                    ]),
                TextInput::make('discount')
                    ->label(__('invoice.discount'))
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->live(debounce: 500)
                    ->suffix('₾'),
                TextInput::make('shipping')
                    ->label(__('invoice.shipping'))
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->live(debounce: 500)
                    ->suffix('₾'),
                DatePicker::make('issued_on')
                    ->label(__('invoice.date'))
                    ->default(now())
                    ->required(),
                DatePicker::make('paid_at')
                    ->label(__('admin.invoices.paid_at'))
                    ->helperText(__('admin.invoices.paid_at_help')),
                Textarea::make('note')
                    ->label(__('admin.invoices.note'))
                    ->helperText(__('admin.invoices.note_help'))
                    ->rows(2)
                    ->maxLength(2000)
                    ->columnSpanFull(),
                Text::make(fn (Get $get): string => __('invoice.total').': '.Catalog::money(static::totalOf((array) ($get('lines') ?? []), $get('discount'), $get('shipping'))))
                    ->weight(FontWeight::Bold)
                    ->size('lg')
                    ->columnSpanFull(),
            ]);
    }

    public static function fillFromProduct(mixed $productId, Set $set): void
    {
        $product = $productId ? Catalog::find((int) $productId) : null;

        if (! $product) {
            return;
        }

        $set('name', $product['name']);
        $set('unit_price', $product['price']);
    }

    public static function totalOf(array $lines, mixed $discount, mixed $shipping): float
    {
        return (new Invoice([
            'lines' => array_values($lines),
            'discount' => (float) $discount,
            'shipping' => (float) $shipping,
        ]))->total();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label(__('invoice.number'))
                    ->formatStateUsing(fn (Invoice $record): string => $record->invoiceNumber())
                    ->weight(FontWeight::Bold)
                    ->sortable(),
                TextColumn::make('issued_on')
                    ->label(__('invoice.date'))
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('buyer_name')
                    ->label(__('invoice.buyer'))
                    ->description(fn (Invoice $record): ?string => collect([$record->buyer_company, $record->buyer_code])->filter()->implode(' · ') ?: null)
                    ->searchable(['buyer_name', 'buyer_company', 'buyer_code', 'phone', 'email']),
                TextColumn::make('phone')
                    ->label(__('checkout.phone'))
                    ->toggleable(),
                TextColumn::make('total')
                    ->label(__('invoice.total'))
                    ->state(fn (Invoice $record): string => Catalog::money($record->total()))
                    ->alignEnd(),
                IconColumn::make('is_paid')
                    ->label(__('invoice.paid'))
                    ->state(fn (Invoice $record): bool => $record->isPaid())
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                TrashActions::filter(),
            ])
            ->recordActions([
                Action::make('print')
                    ->label(__('invoice.print'))
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->url(fn (Invoice $record): string => InvoiceController::manualUrl($record), shouldOpenInNewTab: true),
                EditAction::make()->modalWidth('5xl'),
                ...TrashActions::record(),
            ])
            ->toolbarActions([
                BulkActionGroup::make(TrashActions::bulk()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageInvoices::route('/'),
        ];
    }
}
