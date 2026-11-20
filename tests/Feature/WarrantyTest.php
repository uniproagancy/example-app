<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductSpec;
use App\Models\Setting;
use App\Models\User;
use App\Support\Catalog;
use App\Support\Warranty;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Once;
use Livewire\Livewire;
use Tests\TestCase;

class WarrantyTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->refreshApplicationWithLocale('ka');
    }

    protected function order(Product $product, array $attributes = []): Order
    {
        $order = Order::query()->create([
            'number' => 'EL-'.random_int(100000, 999999), 'status' => 'received', 'first_name' => 'ნინო', 'last_name' => 'ბერიძე', 'phone' => '555 12 34 56',
            'shipping_method' => 'courier', 'city' => 'tbilisi', 'address' => 'რუსთაველი 1',
            'payment_method' => 'cash', 'payment_status' => Order::PAYMENT_PENDING,
            'subtotal' => 450, 'discount' => 0, 'shipping' => 0, 'total' => 450, 'locale' => 'ka',
            ...$attributes,
        ]);
        $order->items()->create(['product_id' => $product->id, 'name' => 'JBL Charge 6', 'unit_price' => 450, 'quantity' => 1, 'total' => 450]);

        return $order;
    }

    protected function refreshWarranty(): void
    {
        Once::flush();
    }

    public function test_warranty_comes_from_the_product_then_the_category_tree_then_the_default(): void
    {
        $product = Product::query()->where('sku', 'jbl')->sole();
        $category = Category::query()->findOrFail($product->category_id);
        $root = $category->parent_id ? Category::query()->findOrFail($category->parent_id) : $category;

        $this->assertSame(Warranty::DEFAULT_MONTHS, Warranty::monthsFor($product->id));

        Setting::put(Warranty::SETTING, '6');
        $this->assertSame(6, Warranty::monthsFor($product->id));

        $root->update(['warranty_months' => 24]);
        $this->refreshWarranty();
        $this->assertSame(24, Warranty::monthsFor($product->id));

        $product->update(['warranty_months' => 36]);
        $this->assertSame(36, Warranty::monthsFor($product->id));

        $this->assertSame('3 წელი', Warranty::label(36));
        $this->assertSame('6 თვე', Warranty::label(6));
    }

    protected function issue(Order $order, string $startsAt = '2026-11-15'): Order
    {
        $order->update(['warranty_starts_at' => $startsAt, 'warranty_issued_at' => now()]);

        return $order->fresh();
    }

    protected function admin(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_order_items_keep_their_warranty_and_it_runs_from_the_confirmed_start(): void
    {
        $product = Product::query()->where('sku', 'jbl')->sole();
        $product->update(['warranty_months' => 24]);

        $order = $this->order($product);
        $item = $order->items()->sole();

        $this->assertSame(24, $item->warranty_months);
        $this->assertSame('pending', $item->warrantyState());

        $product->update(['warranty_months' => 6]);
        $this->travelTo(now()->setDate(2026, 3, 10));
        $order->update(['status' => 'completed']);
        $this->assertNotNull($order->fresh()->completed_at);
        $this->assertSame('pending', $order->fresh()->items()->with('order')->sole()->warrantyState());

        $this->issue($order, '2026-03-10');

        $item = $order->fresh()->items()->with('order')->sole();
        $this->assertSame(24, $item->warranty_months);
        $this->assertSame('09.03.2028', $item->warrantyEndsAt()->format('d.m.Y'));
        $this->assertSame('active', $item->warrantyState());

        $this->travelTo(now()->setDate(2028, 3, 10));
        $this->assertSame('expired', $item->warrantyState());
    }

    public function test_buyer_sees_nothing_until_the_warranty_is_confirmed(): void
    {
        $order = $this->order(Product::query()->where('sku', 'jbl')->sole(), ['status' => 'completed']);

        $this->withSession(['placed_orders' => [$order->id]]);

        $this->get($order->pageUrl())->assertOk()->assertDontSee(__('warranty.view'))->assertDontSee($order->warrantyUrl('ka'), false);
        $this->get($order->invoiceUrl())->assertOk()->assertDontSee(__('warranty.open').': ');
        $this->get($order->warrantyUrl())->assertNotFound();
        $this->get($order->warrantyLink())->assertRedirect($order->warrantyUrl());
        $this->get($order->warrantyUrl())->assertNotFound();

        $order = $this->issue($order);
        $order->items()->update(['serial_number' => 'SN-778899']);

        $this->get($order->pageUrl())->assertOk()->assertSee(__('warranty.view'))->assertSee($order->warrantyUrl('ka'), false);
        $this->get($order->invoiceUrl())->assertOk()->assertSee(__('warranty.open').': '.Warranty::label(Warranty::DEFAULT_MONTHS).' · SN-778899');
        $this->get($order->warrantyUrl())
            ->assertOk()
            ->assertSee(__('warranty.started_note', ['date' => '15.11.2026']))
            ->assertSee('SN-778899')
            ->assertDontSee(__('warranty.preview_note'));
    }

    public function test_signed_link_opens_a_confirmed_certificate_and_plain_access_is_denied(): void
    {
        $order = $this->issue($this->order(Product::query()->where('sku', 'jbl')->sole(), ['status' => 'completed']));

        $this->get('/ka/warranty/'.$order->number)->assertNotFound();
        $this->get('/w/'.$order->number)->assertForbidden();

        $this->get($order->warrantyLink())->assertRedirect($order->warrantyUrl());

        $this->get($order->warrantyUrl())
            ->assertOk()
            ->assertSee(__('warranty.title'))
            ->assertSee('JBL Charge 6')
            ->assertSee(Warranty::label(Warranty::DEFAULT_MONTHS))
            ->assertSee(__('warranty.states.active'))
            ->assertSee('noindex', false);

        $this->get($order->pageUrl())->assertNotFound();

        $order->update(['warranty_issued_at' => null]);
        $this->get($order->warrantyUrl())->assertNotFound();

        $this->issue($order)->update(['status' => 'cancelled']);
        $this->get($order->warrantyUrl())->assertNotFound();
    }

    public function test_admin_fills_in_confirms_sends_and_revokes_the_warranty(): void
    {
        Http::fake(['smsoffice.ge/*' => Http::response(['Success' => true, 'Message' => 'OK', 'Output' => null, 'ErrorCode' => 0])]);
        config(['services.smsoffice.key' => 'sms-key', 'services.smsoffice.sender' => 'ELIVO.GE']);

        $order = $this->order(Product::query()->where('sku', 'jbl')->sole());
        $item = $order->items()->sole();
        $key = 'i'.$item->id;

        $this->admin();

        $this->get($order->warrantyUrl())->assertOk()->assertSee(__('warranty.preview_note'));

        $page = Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertSee(__('admin.warranty.draft_help'))
            ->assertSee(__('admin.warranty.issue'))
            ->assertDontSee($order->warrantyLink(), false)
            ->assertSet("warranty.items.$key.months", Warranty::DEFAULT_MONTHS)
            ->set("warranty.items.$key.months", 0)
            ->call('issueWarranty')
            ->assertHasErrors("warranty.items.$key.months");

        $this->assertNull($order->fresh()->warranty_issued_at);

        $page->set('warranty.starts_at', '2026-11-20')
            ->set("warranty.items.$key.months", 24)
            ->set("warranty.items.$key.serial", ' IMEI 356789104455667 ')
            ->call('issueWarranty')
            ->assertHasNoErrors()
            ->assertNotified(__('admin.warranty.issued'))
            ->assertSee($order->warrantyLink(), false)
            ->assertSee(__('admin.warranty.until', ['date' => '19.11.2028']));

        $order->refresh();
        $item->refresh();
        $this->assertNotNull($order->warranty_issued_at);
        $this->assertSame('2026-11-20', $order->warranty_starts_at->format('Y-m-d'));
        $this->assertSame(24, $item->warranty_months);
        $this->assertSame('IMEI 356789104455667', $item->serial_number);

        $page->call('sendWarrantySms')->assertNotified(__('admin.warranty.sms_sent', ['phone' => $order->phone]));

        Http::assertSent(fn (Request $request) => $request['destination'] === '995555123456'
            && str_contains((string) $request['content'], '/w/'.$order->number.'?signature='));

        $page->call('revokeWarranty')->assertNotified(__('admin.warranty.revoked'));
        $this->assertNull($order->fresh()->warranty_issued_at);

        auth()->logout();
        $this->get($order->warrantyLink())->assertRedirect($order->warrantyUrl());
        $this->get($order->warrantyUrl())->assertNotFound();
    }

    public function test_product_page_shows_our_warranty_and_hides_the_suppliers(): void
    {
        $product = Product::query()->where('sku', 'jbl')->sole();
        $product->update(['warranty_months' => 24]);

        $attribute = Attribute::query()->create(['code' => 'supplier-warranty-test', 'type' => 'select']);
        $attribute->saveTranslations(['ka' => ['name' => 'გარანტია']]);
        $spec = ProductSpec::query()->create(['product_id' => $product->id, 'attribute_id' => $attribute->id, 'position' => 99]);
        $spec->saveTranslations(['ka' => ['value' => '6 თვე მომწოდებლისგან']]);
        Catalog::flush();

        $this->get(Catalog::find($product->id)['url'])
            ->assertOk()
            ->assertSee(__('warranty.perk', ['period' => '2 წელი']))
            ->assertDontSee('6 თვე მომწოდებლისგან');
    }

    public function test_admin_sets_the_default_warranty_in_settings(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageSettings::class)
            ->set('data.'.Warranty::SETTING, 18)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(18, Warranty::defaultMonths());
    }
}
