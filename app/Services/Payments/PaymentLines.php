<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\OrderItem;

class PaymentLines
{
    public function of(Order $order): array
    {
        $lines = $order->items
            ->map(fn (OrderItem $item) => ['item' => $item, 'price' => (float) $item->unit_price, 'qty' => (int) $item->quantity])
            ->values()
            ->all();

        if (! $lines) {
            return [];
        }

        $extra = round((float) $order->total - array_sum(array_map(fn (array $line) => $line['price'] * $line['qty'], $lines)), 2);

        if (abs($extra) < 0.005) {
            return $lines;
        }

        $at = $this->carrier($lines);

        if ($lines[$at]['price'] + $extra <= 0) {
            return $lines;
        }

        if ($lines[$at]['qty'] === 1) {
            $lines[$at]['price'] = round($lines[$at]['price'] + $extra, 2);

            return $lines;
        }

        $lines[$at]['qty']--;

        array_splice($lines, $at + 1, 0, [[
            'item' => $lines[$at]['item'],
            'price' => round($lines[$at]['price'] + $extra, 2),
            'qty' => 1,
        ]]);

        return $lines;
    }

    protected function carrier(array $lines): int
    {
        $singles = array_filter($lines, fn (array $line) => $line['qty'] === 1);
        $pool = $singles ?: $lines;

        uasort($pool, fn (array $a, array $b) => $b['price'] <=> $a['price']);

        return (int) array_key_first($pool);
    }
}
