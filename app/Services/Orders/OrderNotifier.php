<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\Setting;
use App\Notifications\OrderPaidNotification;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\StaffOrderAlert;
use App\Services\Sms\SmsOffice;
use App\Support\Catalog;
use App\Support\Locales;
use App\Support\Site;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class OrderNotifier
{
    public function __construct(public SmsOffice $sms) {}

    public static function enabled(string $key): bool
    {
        return Setting::get($key, '1') === '1';
    }

    public static function staffEmails(): array
    {
        return collect(preg_split('/[\s,;]+/', (string) Setting::get('order_alert_emails')))
            ->map(fn (string $email) => mb_strtolower(trim($email)))
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    public function placed(Order $order): void
    {
        defer(fn () => $this->sendPlaced($order->fresh(['items', 'user']) ?? $order));
    }

    public function paid(Order $order): void
    {
        defer(fn () => $this->sendPaid($order->fresh(['items', 'user']) ?? $order));
    }

    public function sendPlaced(Order $order): void
    {
        $locale = $this->localeOf($order);

        if (static::enabled('notify_buyer_mail') && $this->buyerWants($order, 'mail') && filled($order->email)) {
            $this->attempt('buyer mail', $order, fn () => Notification::route('mail', $order->email)
                ->notify((new OrderPlacedNotification($order))->locale($locale)));
        }

        if (static::enabled('notify_buyer_sms') && $this->buyerWants($order, 'sms')) {
            $this->attempt('buyer sms', $order, fn () => $this->sms->send(
                $order->phone,
                __('notifications.sms.placed', [
                    'number' => $order->number,
                    'amount' => Catalog::money($order->total),
                    'site' => Site::name(),
                ], $locale),
                $order->number,
            ));
        }

        $this->alertStaff($order, StaffOrderAlert::PLACED);

        if (filled(Setting::get('order_alert_phone'))) {
            $this->attempt('staff sms', $order, fn () => $this->sms->send(
                Setting::get('order_alert_phone'),
                __('notifications.sms.staff', [
                    'number' => $order->number,
                    'amount' => Catalog::money($order->total),
                    'name' => trim($order->first_name.' '.$order->last_name),
                    'phone' => $order->phone,
                ], Locales::default()),
            ));
        }
    }

    public function sendPaid(Order $order): void
    {
        if (static::enabled('notify_buyer_mail') && $this->buyerWants($order, 'mail') && filled($order->email)) {
            $this->attempt('buyer paid mail', $order, fn () => Notification::route('mail', $order->email)
                ->notify((new OrderPaidNotification($order))->locale($this->localeOf($order))));
        }

        $this->alertStaff($order, StaffOrderAlert::PAID);
    }

    protected function alertStaff(Order $order, string $event): void
    {
        $emails = static::staffEmails();

        if ($emails === []) {
            return;
        }

        $this->attempt('staff mail', $order, fn () => Notification::route('mail', $emails)
            ->notify((new StaffOrderAlert($order, $event))->locale(Locales::default())));
    }

    protected function buyerWants(Order $order, string $channel): bool
    {
        return $order->user === null || $order->user->wantsNotification($channel);
    }

    protected function localeOf(Order $order): string
    {
        return in_array($order->locale, Locales::codes(), true) ? $order->locale : Locales::default();
    }

    protected function attempt(string $what, Order $order, Closure $send): void
    {
        try {
            $send();
        } catch (Throwable $exception) {
            Log::warning("order notification failed: {$what}", ['order' => $order->number, 'error' => $exception->getMessage()]);
        }
    }
}
