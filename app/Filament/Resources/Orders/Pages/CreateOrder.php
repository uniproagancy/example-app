<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Support\Cart;
use App\Support\Catalog;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return __('admin.orders.create.button');
    }

    public function getBreadcrumb(): string
    {
        return __('admin.orders.create.button');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make(__('admin.orders.create.customer'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('first_name')
                            ->label(__('checkout.first_name'))
                            ->required()
                            ->maxLength(60),
                        TextInput::make('last_name')
                            ->label(__('checkout.last_name'))
                            ->maxLength(60),
                        TextInput::make('phone')
                            ->label(__('checkout.phone'))
                            ->tel()
                            ->required()
                            ->regex('/^\+?[0-9\s\-()]{9,20}$/')
                            ->maxLength(32),
                        TextInput::make('email')
                            ->label(__('checkout.email'))
                            ->email()
                            ->maxLength(255),
                    ]),
                Section::make(__('admin.orders.create.delivery'))
                    ->columns(2)
                    ->schema([
                        Select::make('city')
                            ->label(__('checkout.city'))
                            ->options(collect(Order::CITIES)->mapWithKeys(fn (string $city) => [$city => __("checkout.cities.$city")])->all())
                            ->default('tbilisi')
                            ->required(),
                        TextInput::make('address')
                            ->label(__('checkout.address'))
                            ->maxLength(255),
                        TextInput::make('apartment')
                            ->label(__('checkout.apartment_placeholder'))
                            ->maxLength(255),
                        Select::make('payment_method')
                            ->label(__('admin.orders.payment_method'))
                            ->options(fn () => PaymentMethod::query()->active()->withTranslation()->get()->mapWithKeys(fn (PaymentMethod $method) => [$method->code => $method->name ?? $method->code])->all())
                            ->default(fn () => PaymentMethod::query()->active()->value('code'))
                            ->required(),
                        Textarea::make('note')
                            ->label(__('admin.print.note'))
                            ->rows(2)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),
                Section::make(__('admin.orders.create.products'))
                    ->schema([
                        Repeater::make('products')
                            ->hiddenLabel()
                            ->addActionLabel(__('admin.orders.create.add_product'))
                            ->minItems(1)
                            ->defaultItems(1)
                            ->columns(6)
                            ->schema([
                                Select::make('product_id')
                                    ->label(__('admin.orders.create.product'))
                                    ->searchable()
                                    ->required()
                                    ->getSearchResultsUsing(fn (string $search): array => static::searchProducts($search))
                                    ->getOptionLabelUsing(fn ($value): ?string => static::productLabel((int) $value))
                                    ->live()
                                    ->afterStateUpdated(fn ($state, callable $set) => $set('price', $state ? (Catalog::find((int) $state)['price'] ?? null) : null))
                                    ->columnSpan(4),
                                TextInput::make('quantity')
                                    ->label(__('admin.print.quantity'))
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(Cart::MAX_QUANTITY)
                                    ->default(1)
                                    ->required(),
                                TextInput::make('price')
                                    ->label(__('admin.orders.create.price'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix('₾')
                                    ->required(),
                            ]),
                        Toggle::make('free_shipping')
                            ->label(__('admin.orders.create.free_shipping'))
                            ->helperText(fn () => __('admin.orders.create.shipping_help', ['amount' => Catalog::money(Catalog::SHIPPING_PRICE), 'from' => Catalog::money(Catalog::FREE_SHIPPING_FROM)])),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function searchProducts(string $search): array
    {
        $search = trim($search);

        if (mb_strlen($search) < 2) {
            return [];
        }

        return Product::query()
            ->with('kaTranslation')
            ->where(fn (Builder $query) => $query
                ->where('sku', 'like', "%{$search}%")
                ->orWhere('supplier_sku', 'like', "%{$search}%")
                ->orWhereHas('translations', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")))
            ->orderByDesc('is_active')
            ->orderByDesc('stock')
            ->limit(20)
            ->get()
            ->mapWithKeys(fn (Product $product) => [$product->id => static::describe($product)])
            ->all();
    }

    public static function productLabel(int $id): ?string
    {
        $product = Product::query()->with('kaTranslation')->find($id);

        return $product ? static::describe($product) : null;
    }

    protected static function describe(Product $product): string
    {
        $price = Catalog::find($product->id)['price'] ?? $product->price;

        return ($product->kaTranslation?->name ?? $product->sku).' — '.Catalog::money($price).($product->stock > 0 ? '' : ' · '.__('product.out_of_stock'));
    }

    protected function handleRecordCreation(array $data): Model
    {
        $lines = collect($data['products'] ?? [])
            ->map(function (array $row): ?array {
                $product = Product::query()->with('kaTranslation')->find((int) $row['product_id']);

                if (! $product) {
                    return null;
                }

                return [
                    'product_id' => $product->id,
                    'name' => $product->kaTranslation?->name ?? $product->sku,
                    'price' => round((float) $row['price'], 2),
                    'quantity' => max(1, (int) $row['quantity']),
                ];
            })
            ->filter()
            ->values();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['data.products' => __('admin.orders.create.no_products')]);
        }

        $subtotal = (float) $lines->sum(fn (array $line) => $line['price'] * $line['quantity']);
        $totals = (new Order)->totalsFor($lines->all(), ($data['free_shipping'] ?? false) ? 0 : Cart::shippingFor($subtotal));

        return DB::transaction(function () use ($data, $lines, $totals): Order {
            $order = Order::query()->create([
                'number' => Order::generateNumber(),
                'status' => 'received',
                'source' => 'admin',
                'first_name' => trim($data['first_name']),
                'last_name' => trim((string) ($data['last_name'] ?? '')) ?: null,
                'phone' => trim($data['phone']),
                'email' => filled($data['email'] ?? null) ? mb_strtolower(trim($data['email'])) : null,
                'shipping_method' => 'courier',
                'city' => $data['city'],
                'address' => trim((string) ($data['address'] ?? '')) ?: null,
                'apartment' => trim((string) ($data['apartment'] ?? '')) ?: null,
                'note' => trim((string) ($data['note'] ?? '')) ?: null,
                'payment_method' => $data['payment_method'],
                'payment_status' => Order::PAYMENT_PENDING,
                'subtotal' => $totals['subtotal'],
                'discount' => $totals['discount'],
                'shipping' => $totals['shipping'],
                'total' => $totals['total'],
                'locale' => 'ka',
            ]);

            $order->items()->createMany($lines->map(fn (array $line) => [
                'product_id' => $line['product_id'],
                'name' => $line['name'],
                'unit_price' => $line['price'],
                'quantity' => $line['quantity'],
                'total' => round($line['price'] * $line['quantity'], 2),
            ])->all());

            $order->log('note', __('admin.orders.create.logged'));

            return $order;
        });
    }

    protected function getRedirectUrl(): string
    {
        return OrderResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
