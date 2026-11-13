<?php

namespace App\Notifications\Concerns;

use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Catalog;
use App\Support\Site;

trait DescribesOrder
{
    protected function itemLines(Order $order): array
    {
        return $order->items->map(function (OrderItem $item): string {
            $options = $item->options ? ' ('.implode(', ', (array) $item->options).')' : '';

            return '• '.$item->name.$options.' × '.$item->quantity.' — '.Catalog::money($item->total);
        })->all();
    }

    protected function addressLine(Order $order): ?string
    {
        if (blank($order->address)) {
            return null;
        }

        return collect([
            $order->city ? __("checkout.cities.{$order->city}") : null,
            $order->address,
            $order->apartment,
        ])->filter()->implode(', ');
    }

    public static function noReplyNote(): string
    {
        return filled(Site::phone())
            ? __('notifications.no_reply_phone', ['phone' => Site::phone()])
            : __('notifications.no_reply');
    }

    protected function customerName(Order $order): string
    {
        return trim($order->first_name.' '.$order->last_name);
    }
}
