<?php

namespace App\Notifications;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Notifications\Concerns\DescribesOrder;
use App\Support\Catalog;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffOrderAlert extends Notification
{
    use DescribesOrder;

    public const PLACED = 'placed';

    public const PAID = 'paid';

    public function __construct(public Order $order, public string $event = self::PLACED) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public static function adminUrl(Order $order): string
    {
        return OrderResource::getUrl('view', ['record' => $order], panel: 'admin');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order;
        $address = $this->addressLine($order);

        $mail = (new MailMessage)
            ->subject(__("notifications.staff.{$this->event}_subject", ['number' => $order->number, 'amount' => Catalog::money($order->total)]))
            ->greeting(__("notifications.staff.{$this->event}_greeting", ['number' => $order->number]))
            ->line(__('notifications.staff.customer', ['name' => $this->customerName($order), 'phone' => $order->phone]));

        if (filled($order->email)) {
            $mail->line(__('notifications.staff.email', ['email' => $order->email]));
        }

        if ($address) {
            $mail->line(__('notifications.address', ['address' => $address]));
        }

        $mail->lines($this->itemLines($order))
            ->line(__('notifications.total', ['amount' => Catalog::money($order->total)]))
            ->line(__('notifications.payment', ['method' => $order->paymentLabel() ?? $order->payment_method]));

        if ($order->isQuick()) {
            $mail->line(__('notifications.staff.quick'));
        }

        if (filled($order->note)) {
            $mail->line(__('notifications.staff.note', ['note' => $order->note]));
        }

        return $mail->action(__('notifications.staff.open'), static::adminUrl($order));
    }
}
