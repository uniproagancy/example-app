<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Concerns\DescribesOrder;
use App\Support\Catalog;
use App\Support\Site;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlacedNotification extends Notification
{
    use DescribesOrder;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order;
        $address = $this->addressLine($order);

        $mail = (new MailMessage)
            ->subject(__('notifications.placed.subject', ['number' => $order->number, 'site' => Site::name()]))
            ->greeting(__('notifications.placed.greeting', ['name' => $order->first_name]))
            ->line(__('notifications.placed.intro', ['number' => $order->number]))
            ->lines($this->itemLines($order))
            ->line(__('notifications.total', ['amount' => Catalog::money($order->total)]))
            ->line(__('notifications.payment', ['method' => $order->paymentLabel() ?? $order->payment_method]));

        if ($address) {
            $mail->line(__('notifications.address', ['address' => $address]));
        }

        return $mail
            ->action(__('notifications.view_order'), $order->payLink(30))
            ->line(__('notifications.placed.outro'))
            ->line(static::noReplyNote())
            ->salutation(__('notifications.salutation', ['site' => Site::name()]));
    }
}
