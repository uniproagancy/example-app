<?php

namespace App\Filament\Resources\Deals;

use App\Filament\Resources\Deals\Pages\ManageDeals;
use App\Filament\Support\HasAdminLabels;
use App\Filament\Support\Localized;
use App\Filament\Support\Schedule;
use App\Models\Deal;
use App\Models\Product;
use App\Support\Catalog;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DealResource extends Resource
{
    use HasAdminLabels;

    protected static string $labelKey = 'deals';

    protected static string $groupKey = 'home';

    protected static ?string $model = Deal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFire;

    protected static ?int $navigationSort = 4;

    public static function productOptions(?string $search = null): array
    {
        return Product::query()
            ->with('kaTranslation')
            ->when($search, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('sku', 'like', "%{$search}%")
                ->orWhereHas('translations', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))))
            ->orderBy('position')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Product $product) => [$product->id => ($product->kaTranslation?->name ?? $product->sku).' · '.Catalog::money($product->price)])
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('product_id')
                    ->label(__('admin.deals.product'))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => static::productOptions($search))
                    ->getOptionLabelUsing(fn ($value) => static::productOptions()[$value] ?? Product::query()->find($value)?->kaTranslation?->name)
                    ->options(fn () => static::productOptions())
                    ->required()
                    ->live()
                    ->columnSpanFull(),
                TextInput::make('price')
                    ->label(__('admin.deals.price'))
                    ->helperText(fn (Get $get) => ($regular = Product::query()->find($get('product_id'))?->price)
                        ? __('admin.deals.regular_price').': '.Catalog::money($regular)
                        : __('admin.deals.price_help'))
                    ->numeric()
                    ->minValue(0)
                    ->suffix('₾')
                    ->required(),
                TextInput::make('position')
                    ->label(__('admin.common.position'))
                    ->integer()
                    ->default(0),
                Localized::tabs('deal-text', fn (string $locale) => [
                    Textarea::make("text.$locale")
                        ->label(__('admin.common.text'))
                        ->rows(3),
                ]),
                Schedule::fields(),
                Toggle::make('is_active')
                    ->label(__('admin.common.is_active'))
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description(__('admin.deals.help'))
            ->defaultSort('position')
            ->reorderable('position')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('product.kaTranslation'))
            ->columns([
                TextColumn::make('product.kaTranslation.name')
                    ->label(__('admin.deals.product'))
                    ->wrap(),
                TextColumn::make('price')
                    ->label(__('admin.deals.price'))
                    ->formatStateUsing(fn ($state) => Catalog::money($state))
                    ->description(fn (Deal $record) => $record->product ? __('admin.deals.regular_price').': '.Catalog::money($record->product->price) : null)
                    ->weight(FontWeight::Bold),
                Schedule::column(),
                ToggleColumn::make('is_active')
                    ->label(__('admin.common.is_active')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDeals::route('/'),
        ];
    }
}
