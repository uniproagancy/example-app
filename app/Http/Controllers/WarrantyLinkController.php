<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;

class WarrantyLinkController extends Controller
{
    public function __invoke(string $number): RedirectResponse
    {
        $order = Order::query()->where('number', $number)->firstOrFail();
        $order->rememberWarrantyAccess();

        return redirect()->to($order->warrantyUrl());
    }
}
