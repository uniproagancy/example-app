<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Http\Controllers\InvoiceController;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Support\Company;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function order(array $attributes = []): Order
    {
        $order = Order::query()->create([
            'number' => 'EL-'.random_int(100000, 999999), 'status' => 'received', 'first_name' => 'ნინო', 'last_name' => 'ბერიძე', 'phone' => '555 12 34 56',
            'shipping_method' => 'courier', 'city' => 'tbilisi', 'address' => 'რუსთაველი 1',
            'payment_method' => 'cash', 'payment_status' => Order::PAYMENT_PENDING,
            'subtotal' => 120, 'discount' => 0, 'shipping' => 0, 'total' => 120, 'locale' => 'ka',
            ...$attributes,
        ]);
        $order->items()->create(['name' => 'JBL Charge 6', 'options' => ['შავი'], 'unit_price' => 60, 'quantity' => 2, 'total' => 120]);

        return $order;
    }

    protected function companyDetails(): void
    {
        Setting::put('company_name', 'შპს ელიო');
        Setting::put('company_code', '405123456');
        Setting::put('company_address', 'თბილისი, ვაჟა-ფშაველას 10');
        Setting::put('bank_accounts', json_encode([
            ['bank' => 'საქართველოს ბანკი', 'iban' => 'ge29 bg00 0000 0123 4567 89', 'swift' => 'bagage22', 'currency' => 'GEL'],
            ['bank' => 'ცარიელი', 'iban' => ''],
        ]));
        Setting::put('invoice_note', 'დღგ-ს გადამხდელი არ არის');
    }

    public function test_customer_gets_an_invoice_with_company_and_bank_details(): void
    {
        $this->refreshApplicationWithLocale('ka');
        $this->companyDetails();
        $user = User::factory()->create();
        $order = $this->order(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get($order->invoiceUrl('ka'))
            ->assertOk()
            ->assertSee($order->invoiceNumber())
            ->assertSee('შპს ელიო')
            ->assertSee('405123456')
            ->assertSee('GE29 BG00 0000 0123 4567 89')
            ->assertSee('BAGAGE22')
            ->assertDontSee('ცარიელი')
            ->assertSee('დღგ-ს გადამხდელი არ არის')
            ->assertSee('JBL Charge 6')
            ->assertSee('120 ₾')
            ->assertSee(__('invoice.unpaid'));

        $this->assertSame('INV-'.substr($order->number, 3), $order->invoiceNumber());
    }

    public function test_invoice_is_hidden_from_other_visitors(): void
    {
        $this->refreshApplicationWithLocale('ka');
        $order = $this->order(['user_id' => User::factory()->create()->id]);

        $this->get($order->invoiceUrl('ka'))->assertNotFound();
        $this->actingAs(User::factory()->create())->get($order->invoiceUrl('ka'))->assertNotFound();
        $this->actingAs(User::factory()->operator()->create())->get($order->invoiceUrl('ka'))->assertOk();
    }

    public function test_staff_print_invoices_for_several_orders(): void
    {
        $first = $this->order();
        $second = $this->order(['payment_status' => Order::PAYMENT_PAID, 'paid_at' => now()]);

        $this->get(InvoiceController::adminUrl([$first->id]))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(InvoiceController::adminUrl([$first->id]))->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->get(InvoiceController::adminUrl([$second->id, $first->id]))
            ->assertOk()
            ->assertSeeInOrder([$second->invoiceNumber(), __('invoice.paid'), $first->invoiceNumber(), __('invoice.unpaid')]);
    }

    public function test_invoice_links_appear_in_the_admin_and_the_account(): void
    {
        $this->refreshApplicationWithLocale('ka');
        $user = User::factory()->create();
        $order = $this->order(['user_id' => $user->id]);

        $this->actingAs($user)->get('/ka/account?tab=orders')->assertOk()->assertSee($order->invoiceUrl('ka'), false);
        $this->actingAs($user)->get('/ka/order/'.$order->number)->assertOk()->assertSee($order->invoiceUrl('ka'), false);

        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionHasUrl('invoice', InvoiceController::adminUrl([$order->id]));

        Livewire::test(ListOrders::class)
            ->callTableBulkAction('invoices', [$order])
            ->assertHasNoTableBulkActionErrors();
    }

    public function test_admin_saves_company_and_bank_accounts_in_settings(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageSettings::class)
            ->fillForm([
                'company_name' => 'შპს ელიო',
                'company_code' => '405123456',
                'bank_accounts' => [
                    ['bank' => 'TBC', 'iban' => 'GE00TB0000000000000000', 'swift' => 'TBCBGE22', 'currency' => 'GEL'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('შპს ელიო', Company::details()['name']);
        $this->assertSame('405123456', Company::details()['code']);
        $this->assertSame('GE00TB0000000000000000', Company::bankAccounts()[0]['iban']);
        $this->assertSame('TBC', Company::bankAccounts()[0]['bank']);

        Livewire::test(ManageSettings::class)
            ->assertFormSet(['company_name' => 'შპს ელიო']);
    }
}
