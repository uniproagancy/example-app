<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Filament\Support\HasAdminLabels;
use App\Models\Order;
use App\Models\PaymentMethod;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrderResource extends Resource
{
    use HasAdminLabels;

    protected static string $labelKey = 'orders';

    protected static string $groupKey = 'sales';

    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'number';

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'phone', 'email', 'first_name', 'last_name'];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Order::query()->where('status', 'new')->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function statusOptions(): array
    {
        return collect(Order::STATUSES)->mapWithKeys(fn (string $status) => [$status => __("account.orders.statuses.$status")])->all();
    }

    public static function paymentLabel(?string $method): ?string
    {
        return PaymentMethod::labelFor($method);
    }

    public static function paymentStatusOptions(): array
    {
        return collect(Order::PAYMENT_STATUSES)->mapWithKeys(fn (string $status) => [$status => __("payments.statuses.$status")])->all();
    }

    public static function paymentStatusColor(?string $status): string
    {
        return match ($status) {
            Order::PAYMENT_PAID => 'success',
            Order::PAYMENT_PROCESSING => 'warning',
            Order::PAYMENT_FAILED => 'danger',
            Order::PAYMENT_REFUNDED => 'gray',
            default => 'info',
        };
    }

    public static function sourceOptions(): array
    {
        return collect(Order::SOURCES)->mapWithKeys(fn (string $source) => [$source => __("admin.orders.sources.$source")])->all();
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            'new' => 'warning',
            'received' => 'gray',
            'no_answer' => 'primary',
            'call_later' => 'info',
            'completed' => 'success',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }

    public static function changeStatusAction(): Action
    {
        return Action::make('changeStatus')
            ->label(__('admin.orders.change_status'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->fillForm(fn (Order $record): array => ['status' => $record->status])
            ->schema([
                Select::make('status')
                    ->label(__('admin.common.status'))
                    ->options(static::statusOptions())
                    ->required(),
            ])
            ->action(function (Order $record, array $data): void {
                $from = $record->status;
                $record->update(['status' => $data['status']]);

                if ($from !== $data['status']) {
                    $record->log('status', from: $from, to: $data['status']);
                }

                Notification::make()->title(__('admin.orders.status_changed'))->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
