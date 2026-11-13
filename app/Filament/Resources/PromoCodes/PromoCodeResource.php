<?php

namespace App\Filament\Resources\PromoCodes;

use App\Filament\Resources\PromoCodes\Pages\ManagePromoCodes;
use App\Filament\Support\AdminOnly;
use App\Filament\Support\HasAdminLabels;
use App\Filament\Support\Schedule;
use App\Models\Order;
use App\Models\PromoCode;
use App\Support\Catalog;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PromoCodeResource extends Resource
{
    use AdminOnly;
    use HasAdminLabels;

    protected static string $labelKey = 'promo_codes';

    protected static string $groupKey = 'sales';

    protected static ?string $model = PromoCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('code')
                    ->label(__('admin.promo_codes.code'))
                    ->required()
                    ->alphaDash()
                    ->maxLength(32)
                    ->dehydrateStateUsing(fn (string $state) => mb_strtoupper(trim($state)))
                    ->unique(ignoreRecord: true),
                TextInput::make('percent')
                    ->label(__('admin.promo_codes.percent'))
                    ->integer()
                    ->minValue(1)
                    ->maxValue(90)
                    ->suffix('%')
                    ->required(),
                TextInput::make('min_subtotal')
                    ->label(__('admin.promo_codes.min_subtotal'))
                    ->helperText(__('admin.promo_codes.min_subtotal_help'))
                    ->numeric()
                    ->minValue(0)
                    ->suffix('₾'),
                Toggle::make('is_active')
                    ->label(__('admin.common.is_active'))
                    ->default(true)
                    ->inline(false),
                Schedule::fields(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->addSelect([
                'used_count' => Order::query()->selectRaw('count(*)')->whereColumn('orders.promo_code', 'promo_codes.code'),
            ]))
            ->columns([
                TextColumn::make('code')
                    ->label(__('admin.promo_codes.code'))
                    ->weight(FontWeight::Bold)
                    ->copyable()
                    ->searchable(),
                TextColumn::make('percent')
                    ->label(__('admin.promo_codes.percent'))
                    ->formatStateUsing(fn (int $state) => $state.'%'),
                TextColumn::make('min_subtotal')
                    ->label(__('admin.promo_codes.min_subtotal'))
                    ->formatStateUsing(fn ($state) => $state ? Catalog::money($state) : null)
                    ->placeholder(__('admin.common.none')),
                TextColumn::make('used_count')
                    ->label(__('admin.promo_codes.used'))
                    ->alignCenter(),
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
            'index' => ManagePromoCodes::route('/'),
        ];
    }
}
