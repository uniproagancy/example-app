<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Support\Operators;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\Product;
use App\Support\Catalog;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StoreStats extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        return ! Operators::active();
    }

    public const LOW_STOCK = 3;

    protected static ?int $sort = 1;

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $monthRevenue = Order::query()
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('total');

        return [
            Stat::make(__('admin.dashboard.orders_today'), Order::query()->whereDate('created_at', today())->count())
                ->icon(Heroicon::OutlinedShoppingBag)
                ->url(OrderResource::getUrl()),
            Stat::make(__('admin.dashboard.revenue_month'), Catalog::money($monthRevenue))
                ->description(__('admin.dashboard.revenue_help'))
                ->icon(Heroicon::OutlinedBanknotes),
            Stat::make(__('admin.dashboard.new_orders'), Order::query()->where('status', 'new')->count())
                ->icon(Heroicon::OutlinedInboxArrowDown)
                ->color('warning')
                ->url(OrderResource::getUrl()),
            Stat::make(__('admin.dashboard.new_quick_orders'), Order::query()->where('source', 'quick')->where('status', 'new')->count())
                ->icon(Heroicon::OutlinedBolt)
                ->url(OrderResource::getUrl()),
            Stat::make(__('admin.dashboard.open_messages'), ContactMessage::query()->whereNull('handled_at')->count())
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->url(ContactMessageResource::getUrl()),
            Stat::make(__('admin.dashboard.low_stock'), Product::query()->where('is_active', true)->where('stock', '<=', self::LOW_STOCK)->count())
                ->description(__('admin.dashboard.low_stock_help'))
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('danger')
                ->url(ProductResource::getUrl()),
        ];
    }
}
