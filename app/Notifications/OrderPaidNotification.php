<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Concerns\DescribesOrder;
use App\Support\Catalog;
use App\Support\Site;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPaidNotification extends Notification
{
    use DescribesOrder;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.paid.subject', ['number' => $this->order->number, 'site' => Site::name()]))
            ->greeting(__('notifications.placed.greeting', ['name' => $this->order->first_name]))
            ->line(__('notifications.paid.intro', ['number' => $this->order->number, 'amount' => Catalog::money($this->order->total)]))
            ->action(__('notifications.view_order'), $this->order->payLink(30))
            ->line(__('notifications.paid.outro'))
            ->line(static::noReplyNote())
            ->salutation(__('notifications.salutation', ['site' => Site::name()]));
    }
}
