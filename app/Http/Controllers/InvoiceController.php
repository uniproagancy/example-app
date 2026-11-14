<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public static function adminUrl(array $ids): string
    {
        return route('orders.invoices', ['ids' => implode(',', $ids)]);
    }

    public function show(Request $request, string $number): View
    {
        $order = Order::query()->with('items')->where('number', $number)->firstOrFail();

        abort_unless($order->mayBePaidByVisitor() || $this->isStaff($request), 404);

        return view('orders.invoice', ['orders' => collect([$order]), 'staff' => false]);
    }

    public function admin(Request $request): View
    {
        abort_unless($this->isStaff($request), 403);

        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn (string $id) => (int) trim($id))
            ->filter()
            ->unique()
            ->take(OrderPrintController::MAX_ORDERS)
            ->values();

        abort_if($ids->isEmpty(), 404);

        $orders = Order::withTrashed()
            ->with('items')
            ->whereKey($ids->all())
            ->get()
            ->sortBy(fn (Order $order) => $ids->search($order->id))
            ->values();

        abort_if($orders->isEmpty(), 404);

        return view('orders.invoice', ['orders' => $orders, 'staff' => true]);
    }

    protected function isStaff(Request $request): bool
    {
        return (bool) $request->user()?->canAccessPanel(Filament::getPanel('admin'));
    }
}
