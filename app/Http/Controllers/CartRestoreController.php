<?php

namespace App\Http\Controllers;

use App\Models\AbandonedCart;
use App\Support\Cart;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartRestoreController extends Controller
{
    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $cart = AbandonedCart::query()->where('token', $token)->firstOrFail();
        $locale = in_array($request->query('locale'), Locales::codes(), true) ? $request->query('locale') : Locales::default();

        if ($cart->order_id !== null) {
            return redirect()->to(url($locale));
        }

        Cart::store($cart->items);
        session([AbandonedCart::SESSION_KEY => $cart->token]);

        return redirect()->to(url($locale.'/checkout'));
    }
}
