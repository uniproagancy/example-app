<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderPrintController extends Controller
{
    public const MAX_ORDERS = 200;

    public static function url(array $ids): string
    {
        return route('orders.print', ['ids' => implode(',', $ids)]);
    }

    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->canAccessPanel(Filament::getPanel('admin')), 403);

        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn (string $id) => (int) trim($id))
            ->filter()
            ->unique()
            ->take(self::MAX_ORDERS)
            ->values();

        abort_if($ids->isEmpty(), 404);

        $orders = Order::withTrashed()
            ->with('items')
            ->whereKey($ids->all())
            ->get()
            ->sortBy(fn (Order $order) => $ids->search($order->id))
            ->values();

        abort_if($orders->isEmpty(), 404);

        return view('orders.print', ['orders' => $orders]);
    }
}
