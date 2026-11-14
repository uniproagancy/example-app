<?php

namespace App\Notifications;

use App\Notifications\Concerns\DescribesOrder;
use App\Support\Catalog;
use App\Support\Site;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PriceDropNotification extends Notification
{
    use DescribesOrder;

    public function __construct(public string $productName, public string $url, public float $oldPrice, public float $newPrice) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.price_drop.subject', ['product' => $this->productName, 'site' => Site::name()]))
            ->greeting(__('notifications.price_drop.greeting'))
            ->line(__('notifications.price_drop.intro', ['product' => $this->productName]))
            ->line(__('notifications.price_drop.prices', ['old' => Catalog::money($this->oldPrice), 'new' => Catalog::money($this->newPrice)]))
            ->action(__('notifications.price_drop.action'), $this->url)
            ->line(__('notifications.price_drop.outro'))
            ->line(static::noReplyNote())
            ->salutation(__('notifications.salutation', ['site' => Site::name()]));
    }
}
