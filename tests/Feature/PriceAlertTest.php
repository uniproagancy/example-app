<?php

namespace Tests\Feature;

use App\Models\PriceAlert;
use App\Models\Product;
use App\Notifications\PriceDropNotification;
use App\Support\Catalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class PriceAlertTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function speaker(): Product
    {
        return Product::query()->where('sku', 'jbl')->first();
    }

    public function test_shopper_subscribes_with_an_email_or_a_phone(): void
    {
        $speaker = $this->speaker();
        $price = Catalog::find($speaker->id)['price'];

        Livewire::test('price-alert', ['productId' => $speaker->id])
            ->set('contact', 'Nino@Mail.ge')
            ->call('subscribe')
            ->assertHasNoErrors()
            ->assertSee(__('product.price_alert.sent'));

        Livewire::test('price-alert', ['productId' => $speaker->id])
            ->set('contact', '555 12-34-56')
            ->call('subscribe')
            ->assertHasNoErrors();

        Livewire::test('price-alert', ['productId' => $speaker->id])
            ->set('contact', 'not a contact')
            ->call('subscribe')
            ->assertHasErrors('contact');

        $this->assertEqualsCanonicalizing(['nino@mail.ge', '555123456'], PriceAlert::query()->pluck('contact')->all());
        $this->assertSame(['mail', 'sms'], PriceAlert::query()->orderBy('id')->pluck('channel')->all());
        $this->assertEquals($price, (float) PriceAlert::query()->first()->price);
    }

    public function test_product_page_offers_the_alert(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $this->get(Catalog::find($this->speaker()->id)['url'])
            ->assertOk()
            ->assertSee(__('product.price_alert.title'));
    }

    public function test_alerts_go_out_only_when_the_price_drops(): void
    {
        Notification::fake();
        Http::fake(['smsoffice.ge/*' => Http::response(['Success' => true, 'Message' => 'OK', 'Output' => null, 'ErrorCode' => 0])]);
        config(['services.smsoffice.key' => 'sms-key', 'services.smsoffice.sender' => 'ELIVO.GE']);

        $speaker = $this->speaker();
        $current = (float) Catalog::find($speaker->id)['price'];

        $cheaper = PriceAlert::factory()->create(['product_id' => $speaker->id, 'contact' => 'nino@mail.ge', 'price' => $current + 50]);
        $sms = PriceAlert::factory()->sms()->create(['product_id' => $speaker->id, 'contact' => '555123456', 'price' => $current + 20]);
        $same = PriceAlert::factory()->create(['product_id' => $speaker->id, 'contact' => 'same@mail.ge', 'price' => $current]);

        $this->artisan('price-alerts:send')->assertSuccessful();

        Notification::assertSentTo(
            new AnonymousNotifiable,
            PriceDropNotification::class,
            fn (PriceDropNotification $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'nino@mail.ge'
                && $notification->newPrice === $current,
        );
        Http::assertSent(fn (Request $request) => $request['destination'] === '995555123456' || str_contains((string) $request['destination'], '555123456'));

        $this->assertNotNull($cheaper->fresh()->notified_at);
        $this->assertNotNull($sms->fresh()->notified_at);
        $this->assertNull($same->fresh()->notified_at);

        $this->artisan('price-alerts:send')->assertSuccessful();
        Notification::assertSentTimes(PriceDropNotification::class, 1);
    }
}
