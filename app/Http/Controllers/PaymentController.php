<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentCallback;
use App\Services\Payments\Drivers\BogInstallment;
use App\Services\Payments\Drivers\CredoInstallment;
use App\Services\Payments\Drivers\CredoSplit;
use App\Services\Payments\PaymentCheck;
use App\Services\Payments\PaymentManager;
use App\Support\Redact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(protected PaymentManager $payments) {}

    public function callback(Request $request, string $driver): JsonResponse
    {
        $started = microtime(true);
        $record = PaymentCallback::receive($request, $driver);

        Log::channel('payments')->info('callback received', [
            'driver' => $driver,
            'record' => $record?->id,
            'payload' => Redact::secrets(json_encode($request->all(), JSON_UNESCAPED_UNICODE)),
        ]);

        $outcome = ['outcome' => PaymentCallback::UNKNOWN_DRIVER];

        if ($this->payments->has($driver)) {
            $outcome = ['outcome' => PaymentCallback::UNMATCHED];

            try {
                $provider = $this->payments->driver($driver);
                $transaction = $provider->handleCallback($request->all());

                if ($transaction) {
                    $outcome += [
                        'payment_transaction_id' => $transaction->id,
                        'order_id' => $transaction->order_id,
                        'status_before' => $transaction->status,
                    ];

                    $paid = $provider->confirm($transaction);
                    $this->payments->settle($transaction, $paid);

                    $outcome['outcome'] = $paid ? PaymentCallback::PAID : PaymentCallback::NOT_PAID;
                    $outcome['confirmed'] = $paid;
                    $outcome['status_after'] = $transaction->fresh()?->status;
                }
            } catch (Throwable $e) {
                $outcome['outcome'] = PaymentCallback::FAILED;
                $outcome['error'] = mb_substr($e->getMessage(), 0, 2000);

                Log::channel('payments')->error('callback failed', [
                    'driver' => $driver,
                    'record' => $record?->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $record?->finish($outcome + ['duration_ms' => (int) round((microtime(true) - $started) * 1000)]);

        return response()->json(['ok' => true]);
    }

    public function return(Request $request, string $number): RedirectResponse
    {
        $order = Order::query()->where('number', $number)->firstOrFail();

        if ($request->hasValidSignature()) {
            $order->rememberInSession();
        }

        if ($request->query('status', 'success') !== 'success') {
            session()->flash('payment_failed', true);
        }

        if (! $order->isPaid()) {
            app(PaymentCheck::class)->ask($order);
        }

        return redirect()->to($order->pageUrl());
    }

    public function link(string $number): RedirectResponse
    {
        $order = Order::query()->where('number', $number)->firstOrFail();
        $order->rememberInSession();

        return redirect()->to($order->pageUrl());
    }

    public function bogInstallment(Request $request, string $number): JsonResponse
    {
        $data = $request->validate([
            'month' => ['required', 'integer', 'min:1', 'max:48'],
        ]);

        $order = Order::query()->where('number', $number)->firstOrFail();

        abort_unless($request->hasValidSignature() || $order->mayBePaidByVisitor(), 403);

        if ($order->isPaid()) {
            return response()->json(['error' => __('payments.already_paid')], 409);
        }

        try {
            $transaction = $this->payments->start($order, 'bog-installment', [
                'months' => (int) $data['month'],
                'type' => $order->paymentMethod?->installment_type ?: BogInstallment::STANDARD,
            ]);
        } catch (Throwable $e) {
            Log::channel('payments')->error('bog application failed', [
                'order' => $order->number,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => __('payments.failed')], 422);
        }

        return response()->json([
            'orderId' => $transaction->external_id,
            'redirectUrl' => $transaction->redirect_url,
        ]);
    }

    public function credo(Request $request, string $number, CredoInstallment $credo): View|RedirectResponse
    {
        return $this->handOverToCredo($request, $number, $credo);
    }

    public function credoSplit(Request $request, string $number, CredoSplit $credo): View|RedirectResponse
    {
        return $this->handOverToCredo($request, $number, $credo);
    }

    protected function handOverToCredo(Request $request, string $number, CredoInstallment $credo): View|RedirectResponse
    {
        $order = Order::query()->with('items')->where('number', $number)->firstOrFail();

        abort_unless($request->hasValidSignature() || $order->mayBePaidByVisitor(), 404);

        if ($order->isPaid()) {
            return redirect()->to($order->pageUrl());
        }

        return view('payments.credo', [
            'action' => $credo->widgetUrl(),
            'field' => $credo->fieldName(),
            'label' => $credo->label(),
            'data' => json_encode($credo->payload($order), JSON_UNESCAPED_UNICODE),
        ]);
    }
}
