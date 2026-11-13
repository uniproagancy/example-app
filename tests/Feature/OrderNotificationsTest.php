<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\OrderPaidNotification;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\StaffOrderAlert;
use App\Services\Sms\SmsOffice;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class OrderNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutDefer();
        Notification::fake();
        Http::fake(['smsoffice.ge/*' => Http::response(['Success' => true, 'Message' => 'OK', 'Output' => null, 'ErrorCode' => 0])]);
        config(['services.smsoffice.key' => 'sms-key', 'services.smsoffice.sender' => 'ELIVO.GE']);
        Setting::put('order_alert_emails', 'orders@elivo.test, boss@elivo.test');
        Setting::put('order_alert_phone', '599 11 22 33');
        Setting::put('email', 'info@elivo.test');
    }

    protected function placeOrder(): Order
    {
        $speaker = Product::query()->where('sku', 'jbl')->first();
        session(['cart' => [(string) $speaker->id => 2]]);

        Livewire::test('pages::checkout')
            ->set('firstName', 'ნინო')
            ->set('lastName', 'ბერიძე')
            ->set('phone', '+995 555 12 34 56')
            ->set('email', 'nino@mail.ge')
            ->set('address', 'ჭავჭავაძის გამზ. 21')
            ->call('choose', 'payment', 'transfer')
            ->call('placeOrder')
            ->assertHasNoErrors();

        return Order::query()->with('items')->sole();
    }

    public function test_a_new_order_mails_and_texts_the_buyer_and_alerts_the_staff(): void
    {
        $order = $this->placeOrder();

        Notification::assertSentOnDemand(OrderPlacedNotification::class, function (OrderPlacedNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($order) {
            $mail = $notification->toMail($notifiable);

            return $notifiable->routes['mail'] === 'nino@mail.ge'
                && $notification->order->is($order)
                && str_contains($mail->subject, $order->number)
                && collect($mail->introLines)->contains(fn (string $line) => str_contains($line, $order->items->first()->name) && str_contains($line, '× 2'))
                && str_contains($mail->actionUrl, '/payment/link/'.$order->number)
                && $mail->replyTo === []
                && in_array(OrderPlacedNotification::noReplyNote(), $mail->outroLines, true);
        });

        Notification::assertSentOnDemand(StaffOrderAlert::class, function (StaffOrderAlert $notification, array $channels, AnonymousNotifiable $notifiable) use ($order) {
            $mail = $notification->toMail($notifiable);

            return $notifiable->routes['mail'] === ['orders@elivo.test', 'boss@elivo.test']
                && $notification->event === StaffOrderAlert::PLACED
                && collect($mail->introLines)->contains(fn (string $line) => str_contains($line, '+995 555 12 34 56'))
                && str_contains($mail->actionUrl, '/admin/orders/'.$order->getRouteKey());
        });

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://smsoffice.ge/api/v2/send/')
            && $request['key'] === 'sms-key'
            && $request['sender'] === 'ELIVO.GE'
            && $request['destination'] === '995555123456'
            && str_contains($request['content'], $order->number));

        Http::assertSent(fn (Request $request) => $request['destination'] === '995599112233'
            && str_contains($request['content'], 'ნინო ბერიძე'));
    }

    public function test_paying_an_order_mails_the_buyer_and_the_staff_once(): void
    {
        $order = $this->placeOrder();
        Notification::fake();

        $order->setPaymentStatus(Order::PAYMENT_PAID);
        $order->update(['note' => 'changed']);

        Notification::assertSentOnDemandTimes(OrderPaidNotification::class, 1);
        Notification::assertSentOnDemand(StaffOrderAlert::class, fn (StaffOrderAlert $notification) => $notification->event === StaffOrderAlert::PAID);
    }

    public function test_buyer_preferences_and_admin_switches_are_respected(): void
    {
        $user = User::factory()->create(['notification_preferences' => ['sms' => false, 'mail' => true, 'promo' => false]]);
        $this->actingAs($user);
        Setting::put('notify_buyer_mail', '0');
        Setting::put('order_alert_phone', null);

        $this->placeOrder();

        Notification::assertSentOnDemandTimes(OrderPlacedNotification::class, 0);
        Notification::assertSentOnDemandTimes(StaffOrderAlert::class, 1);
        Http::assertNothingSent();
    }

    public function test_a_failing_sms_gateway_does_not_break_the_order(): void
    {
        Http::fake(['smsoffice.ge/*' => Http::response(['Success' => false, 'Message' => 'Balance is insufficient', 'ErrorCode' => 20])]);

        $order = $this->placeOrder();

        $this->assertNotNull($order->number);
        Notification::assertSentOnDemandTimes(OrderPlacedNotification::class, 1);
    }

    public function test_quick_orders_alert_the_staff(): void
    {
        $speaker = Product::query()->where('sku', 'jbl')->first();

        Livewire::test('quick-order', ['productId' => $speaker->id])
            ->set('name', 'გიორგი')
            ->set('phone', '577 00 00 01')
            ->set('payment', 'cash')
            ->call('submit')
            ->assertHasNoErrors();

        Notification::assertSentOnDemand(StaffOrderAlert::class, fn (StaffOrderAlert $notification, array $channels, AnonymousNotifiable $notifiable) => collect($notification->toMail($notifiable)->introLines)->contains(__('notifications.staff.quick')));
        Http::assertSent(fn (Request $request) => $request['destination'] === '995577000001');
    }

    public function test_phone_numbers_are_normalized_for_georgia(): void
    {
        $this->assertSame('995555123456', SmsOffice::normalize('+995 555 12 34 56'));
        $this->assertSame('995555123456', SmsOffice::normalize('555-12-34-56'));
        $this->assertSame('995555123456', SmsOffice::normalize('00995555123456'));
        $this->assertNull(SmsOffice::normalize('032 2 00 00 00'));
        $this->assertNull(SmsOffice::normalize(null));
    }

    public function test_admin_saves_notification_settings(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageSettings::class)
            ->fillForm(['order_alert_emails' => 'a@elivo.test, nope'])
            ->call('save')
            ->assertHasFormErrors(['order_alert_emails']);

        Livewire::test(ManageSettings::class)
            ->fillForm(['order_alert_emails' => 'a@elivo.test', 'notify_buyer_sms' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('a@elivo.test', Setting::get('order_alert_emails'));
        $this->assertSame('0', Setting::get('notify_buyer_sms'));
        $this->assertSame('1', Setting::get('notify_buyer_mail'));
    }
}
