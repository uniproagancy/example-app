<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Notifications\OrderPlacedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BankTransferTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::put('bank_accounts', json_encode([['bank' => 'საქართველოს ბანკი', 'iban' => 'GE29BG0000000123456789', 'swift' => 'BAGAGE22', 'currency' => 'GEL']]));
    }

    public function test_transfer_order_shows_bank_details_and_invoice_after_checkout(): void
    {
        $speaker = Product::query()->where('sku', 'jbl')->first();
        session(['cart' => [(string) $speaker->id => 1]]);

        Livewire::test('pages::checkout')
            ->set('firstName', 'ნინო')
            ->set('lastName', 'ბერიძე')
            ->set('phone', '555 12 34 56')
            ->set('email', 'nino@mail.ge')
            ->set('address', 'რუსთაველი 1')
            ->call('choose', 'payment', 'transfer')
            ->call('placeOrder')
            ->assertHasNoErrors()
            ->assertSee(__('payments.transfer.title'))
            ->assertSee('GE29 BG00 0000 0123 4567 89')
            ->assertSee(Order::query()->sole()->invoiceNumber());
    }

    public function test_placed_mail_lists_the_accounts_for_transfer_orders_only(): void
    {
        $transfer = Order::query()->create([
            'number' => 'EL-222222', 'status' => 'new', 'first_name' => 'ნინო', 'phone' => '555123456', 'payment_method' => 'transfer',
            'payment_status' => 'pending', 'subtotal' => 300, 'discount' => 0, 'shipping' => 0, 'total' => 300, 'locale' => 'ka',
        ]);
        $cash = $transfer->replicate()->fill(['number' => 'EL-333333', 'payment_method' => 'cash']);
        $cash->save();

        $transferMail = implode("\n", (new OrderPlacedNotification($transfer))->toMail($transfer)->introLines);
        $cashMail = implode("\n", (new OrderPlacedNotification($cash))->toMail($cash)->introLines);

        $this->assertStringContainsString('GE29BG0000000123456789', $transferMail);
        $this->assertStringContainsString('INV-222222', $transferMail);
        $this->assertStringNotContainsString('GE29BG0000000123456789', $cashMail);
    }
}
