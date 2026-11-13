<?php

namespace App\Filament\Resources\PaymentMethods;

use App\Filament\Resources\PaymentMethods\Pages\ManagePaymentMethods;
use App\Filament\Support\AdminOnly;
use App\Filament\Support\HasAdminLabels;
use App\Filament\Support\Localized;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\Payments\Drivers\BogInstallment;
use App\Services\Payments\PaymentManager;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentMethodResource extends Resource
{
    use AdminOnly;
    use HasAdminLabels;

    protected static string $labelKey = 'payment_methods';

    protected static string $groupKey = 'sales';

    protected static ?string $model = PaymentMethod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 2;

    public const LOGOS = [
        '/img/payments/bog-1.png',
        '/img/payments/bog-2.png',
        '/img/payments/tbc-1.png',
        '/img/payments/credo-1.png',
        '/img/payments/credo-2.png',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Localized::relationTabs('payment-method-translations', 'name', fn (string $locale, bool $isPrimary) => [
                    TextInput::make('name')
                        ->label(__('admin.payment_methods.name'))
                        ->required($isPrimary)
                        ->maxLength(255),
                    TextInput::make('note')
                        ->label(__('admin.payment_methods.note'))
                        ->helperText(__('admin.payment_methods.note_help'))
                        ->maxLength(255),
                ]),
                TextInput::make('code')
                    ->label(__('admin.payment_methods.code'))
                    ->helperText(__('admin.payment_methods.code_help'))
                    ->required()
                    ->alphaDash()
                    ->maxLength(40)
                    ->unique(ignoreRecord: true)
                    ->disabledOn('edit'),
                TextInput::make('logo')
                    ->label(__('admin.payment_methods.logo'))
                    ->helperText(__('admin.payment_methods.logo_help'))
                    ->datalist(self::LOGOS)
                    ->maxLength(255),
                Select::make('driver')
                    ->label(__('admin.payment_methods.driver'))
                    ->helperText(fn (Get $get): string => static::driverHint($get('driver')))
                    ->options(PaymentManager::driverOptions())
                    ->placeholder(__('admin.payment_methods.offline'))
                    ->live()
                    ->afterStateUpdated(fn (?string $state, callable $set) => $set('is_online', filled($state))),
                Select::make('installment_type')
                    ->label(__('admin.payment_methods.installment_type'))
                    ->options([
                        BogInstallment::STANDARD => __('admin.payment_methods.types.STANDARD'),
                        BogInstallment::ZERO => __('admin.payment_methods.types.ZERO'),
                    ])
                    ->default(BogInstallment::STANDARD)
                    ->visible(fn (Get $get): bool => $get('driver') === 'bog-installment'),
                Grid::make(2)
                    ->schema([
                        TextInput::make('min_total')
                            ->label(__('admin.payment_methods.min_total'))
                            ->numeric()
                            ->minValue(0)
                            ->suffix('₾'),
                        TextInput::make('max_total')
                            ->label(__('admin.payment_methods.max_total'))
                            ->numeric()
                            ->minValue(0)
                            ->gt('min_total')
                            ->suffix('₾'),
                    ])
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label(__('admin.common.is_active'))
                    ->default(true),
                Toggle::make('is_online')
                    ->label(__('admin.payment_methods.is_online'))
                    ->helperText(__('admin.payment_methods.is_online_help'))
                    ->disabled(fn (Get $get): bool => blank($get('driver')))
                    ->dehydrateStateUsing(fn (?bool $state, Get $get): bool => filled($get('driver')) && (bool) $state),
                Toggle::make('on_quick_order')
                    ->label(__('admin.payment_methods.on_quick_order'))
                    ->default(true),
                TextInput::make('sort_order')
                    ->label(__('admin.common.position'))
                    ->integer()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('kaTranslation'))
            ->columns([
                ImageColumn::make('logo')
                    ->label('')
                    ->state(fn (PaymentMethod $record): ?string => $record->logoUrl())
                    ->imageHeight(28),
                TextColumn::make('kaTranslation.name')
                    ->label(__('admin.payment_methods.name'))
                    ->description(fn (PaymentMethod $record): string => $record->code)
                    ->weight('bold')
                    ->wrap(),
                TextColumn::make('driver')
                    ->label(__('admin.payment_methods.driver'))
                    ->state(fn (PaymentMethod $record): string => $record->driver ? __("payments.drivers.{$record->driver}") : __('admin.payment_methods.offline'))
                    ->badge()
                    ->color(fn (PaymentMethod $record): string => static::readinessColor($record))
                    ->description(fn (PaymentMethod $record): ?string => static::readinessNote($record)),
                TextColumn::make('min_total')
                    ->label(__('admin.payment_methods.limits'))
                    ->state(fn (PaymentMethod $record): string => static::limits($record)),
                ToggleColumn::make('on_quick_order')
                    ->label(__('admin.payment_methods.on_quick_order_short')),
                ToggleColumn::make('is_active')
                    ->label(__('admin.common.is_active')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, PaymentMethod $record): void {
                        if (Order::query()->where('payment_method', $record->code)->exists()) {
                            Notification::make()->title(__('admin.payment_methods.cannot_delete'))->danger()->send();

                            $action->halt();
                        }
                    }),
            ]);
    }

    public static function driverHint(?string $driver): string
    {
        if (! $driver) {
            return __('admin.payment_methods.driver_help');
        }

        return app(PaymentManager::class)->isReady($driver)
            ? __('admin.payment_methods.ready')
            : __('admin.payment_methods.missing_keys', ['keys' => static::envKeys($driver)]);
    }

    public static function envKeys(string $driver): string
    {
        return match ($driver) {
            'flitt' => 'FLITT_MERCHANT_ID, FLITT_SECRET_KEY',
            'bog-installment' => 'BOG_INSTALLMENT_PUBLIC_KEY, BOG_INSTALLMENT_SECRET_KEY, BOG_SDK_CLIENT_ID',
            'tbc-installment' => 'TBC_CLIENT_ID, TBC_CLIENT_SECRET, TBC_MERCHANT_KEY, TBC_CAMPAIGN_ID',
            'credo-installment' => 'CREDO_MERCHANT_ID, CREDO_SECRET',
            'credo-split' => 'CREDO_SPLIT_MERCHANT_ID, CREDO_SPLIT_SECRET',
            default => '',
        };
    }

    protected static function readinessColor(PaymentMethod $record): string
    {
        if (! $record->is_online || ! $record->driver) {
            return 'gray';
        }

        return app(PaymentManager::class)->isReady($record->driver) ? 'success' : 'warning';
    }

    protected static function readinessNote(PaymentMethod $record): ?string
    {
        if (! $record->is_online || ! $record->driver) {
            return null;
        }

        return app(PaymentManager::class)->isReady($record->driver)
            ? __('admin.payment_methods.ready_short')
            : __('admin.payment_methods.missing_short');
    }

    protected static function limits(PaymentMethod $record): string
    {
        $min = $record->min_total !== null ? (float) $record->min_total : null;
        $max = $record->max_total !== null ? (float) $record->max_total : null;

        return match (true) {
            $min !== null && $max !== null => number_format($min, 0).' – '.number_format($max, 0).' ₾',
            $min !== null => __('admin.payment_methods.from', ['amount' => number_format($min, 0)]),
            $max !== null => __('admin.payment_methods.up_to', ['amount' => number_format($max, 0)]),
            default => __('admin.payment_methods.any_amount'),
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePaymentMethods::route('/'),
        ];
    }
}
