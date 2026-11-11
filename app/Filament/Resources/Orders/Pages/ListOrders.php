<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('shop.all')),
            ...collect(Order::STATUSES)->mapWithKeys(fn (string $status) => [
                $status => Tab::make(__("account.orders.statuses.$status"))
                    ->badge(fn () => Order::query()->where('status', $status)->count() ?: null)
                    ->badgeColor(OrderResource::statusColor($status))
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status)),
            ])->all(),
        ];
    }
}
