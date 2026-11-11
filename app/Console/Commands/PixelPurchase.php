<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderPixelData;
use App\Services\Facebook\Pixel;
use App\Support\Catalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PixelPurchase extends Command
{
    protected $signature = 'pixel:purchase
        {number? : the order number, as the shop writes it}
        {--missing : every sale Meta has not been told about}
        {--dry : say what would be reported and report nothing}';

    protected $description = 'Report a sale to Meta that was never reported';

    public function handle(Pixel $pixel): int
    {
        $orders = $this->orders();

        if ($orders->isEmpty()) {
            $this->line('Nothing to report.');

            return self::SUCCESS;
        }

        $reported = $skipped = 0;

        foreach ($orders as $order) {
            $snapshot = OrderPixelData::where('order_id', $order->id)->first();
            $why = $this->inTheWay($order, $snapshot);

            if ($why) {
                $this->line('  '.$order->number.'  '.$why);
                $skipped++;

                continue;
            }

            if ($this->option('dry')) {
                $this->line('  '.$order->number.'  would be reported, '.Catalog::money($order->total));
                $reported++;

                continue;
            }

            $pixel->snapshotForStaffOrder($order);

            if ($pixel->reportPurchase($order, offline: true)) {
                $this->info('  '.$order->number.'  reported, '.Catalog::money($order->total));
                $reported++;

                continue;
            }

            $this->error('  '.$order->number.'  Meta refused it; see the pixel log');
            $skipped++;
        }

        $this->line('');
        $this->line($reported.($this->option('dry') ? ' to report' : ' reported').', '.$skipped.' left');

        return self::SUCCESS;
    }

    protected function orders()
    {
        if ($number = $this->argument('number')) {
            return Order::where('number', $number)->with('items')->get();
        }

        if (! $this->option('missing')) {
            $this->error('Name an order, or ask for --missing.');

            return collect();
        }

        return Order::query()
            ->where('status', '!=', Pixel::CANCELLED)
            ->where(fn ($q) => $q->where('payment_status', Order::PAYMENT_PAID)->orWhere('status', Pixel::COMPLETED))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('order_pixel_data')
                ->whereColumn('order_pixel_data.order_id', 'orders.id')
                ->whereNotNull('sent_at'))
            ->with('items')
            ->orderBy('id')
            ->get();
    }

    protected function inTheWay(Order $order, ?OrderPixelData $snapshot): ?string
    {
        if ($order->status === Pixel::CANCELLED) {
            return 'cancelled';
        }

        if (! $order->isPaid() && $order->status !== Pixel::COMPLETED) {
            return 'not a sale yet: '.$order->status.', unpaid';
        }

        if ($snapshot?->alreadySent()) {
            return 'already reported on '.$snapshot->sent_at->toDateTimeString();
        }

        return null;
    }
}
