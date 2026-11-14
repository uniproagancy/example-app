<?php

namespace App\Console\Commands;

use App\Models\PriceAlert;
use App\Notifications\PriceDropNotification;
use App\Services\Sms\SmsOffice;
use App\Support\Catalog;
use App\Support\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SendPriceAlerts extends Command
{
    protected $signature = 'price-alerts:send';

    protected $description = 'Tell shoppers who asked about a product that its price went down';

    public function handle(SmsOffice $sms): int
    {
        $original = app()->getLocale();
        $sent = 0;

        PriceAlert::query()->waiting()->orderBy('locale')->orderBy('id')->chunkById(200, function ($alerts) use ($sms, &$sent): void {
            foreach ($alerts as $alert) {
                app()->setLocale($alert->locale);
                $product = Catalog::find($alert->product_id);

                if (! $product || ! $product['inStock'] || (float) $product['price'] >= (float) $alert->price) {
                    continue;
                }

                if ($this->notify($sms, $alert, $product)) {
                    $alert->update(['notified_at' => now(), 'notified_price' => $product['price']]);
                    $sent++;
                }
            }
        });

        app()->setLocale($original);
        $this->info("Sent {$sent} price alert(s).");

        return self::SUCCESS;
    }

    protected function notify(SmsOffice $sms, PriceAlert $alert, array $product): bool
    {
        try {
            if ($alert->channel === 'mail') {
                Notification::route('mail', $alert->contact)->notify(
                    (new PriceDropNotification($product['name'], $product['url'], (float) $alert->price, (float) $product['price']))->locale($alert->locale),
                );

                return true;
            }

            return $sms->send($alert->contact, __('notifications.sms.price_drop', [
                'site' => Site::name(),
                'product' => mb_strimwidth($product['name'], 0, 60, '…'),
                'price' => Catalog::money($product['price']),
                'url' => $product['url'],
            ], $alert->locale), 'price-'.$alert->id);
        } catch (Throwable $exception) {
            Log::warning('price alert failed', ['alert' => $alert->id, 'error' => $exception->getMessage()]);

            return false;
        }
    }
}
