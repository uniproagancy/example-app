<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\Brands\BrandResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\Deals\DealResource;
use App\Filament\Resources\Faqs\FaqResource;
use App\Filament\Resources\HomeBlocks\HomeBlockResource;
use App\Filament\Resources\MenuItems\MenuItemResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Perks\PerkResource;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\PromoCodes\PromoCodeResource;
use App\Filament\Resources\Promos\PromoResource;
use App\Filament\Resources\Slides\SlideResource;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\LatestOrders;
use App\Filament\Widgets\StoreStats;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\Catalog;
use App\Support\Site;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_only_admins_can_open_the_panel(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');

        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();

        $this->actingAs($this->admin)->get('/admin')->assertOk();

        Livewire::test(StoreStats::class)
            ->assertSee(__('admin.dashboard.orders_today', [], 'ka'))
            ->assertSee(__('admin.dashboard.low_stock', [], 'ka'));

        Livewire::test(LatestOrders::class)->assertSee(__('admin.dashboard.latest_orders', [], 'ka'));
    }

    public function test_every_admin_page_renders(): void
    {
        $this->actingAs($this->admin);

        $product = Product::query()->first();
        $category = Category::query()->first();

        $urls = [
            ProductResource::getUrl(),
            ProductResource::getUrl('create'),
            ProductResource::getUrl('edit', ['record' => $product]),
            CategoryResource::getUrl(),
            CategoryResource::getUrl('create'),
            CategoryResource::getUrl('edit', ['record' => $category]),
            BrandResource::getUrl(),
            OrderResource::getUrl(),
            ContactMessageResource::getUrl(),
            PromoCodeResource::getUrl(),
            SlideResource::getUrl(),
            HomeBlockResource::getUrl(),
            PerkResource::getUrl(),
            PromoResource::getUrl(),
            DealResource::getUrl(),
            FaqResource::getUrl(),
            MenuItemResource::getUrl(),
            UserResource::getUrl(),
            ManageSettings::getUrl(),
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_admin_creates_a_product_with_translations(): void
    {
        $this->actingAs($this->admin);

        $category = Category::query()->where('code', 'gaming')->first();
        $brand = Brand::query()->where('name', 'Sony')->first();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'kaTranslation' => ['name' => 'სატესტო კონსოლი', 'subtitle' => 'ახალი თაობა', 'description' => 'აღწერა'],
                'enTranslation' => ['name' => 'Test Console'],
                'price' => 1299,
                'old_price' => 1499,
                'stock' => 4,
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'sku' => 'test-console',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('sku', 'test-console')->first();

        $this->assertNotNull($product);
        $this->assertSame('სატესტო კონსოლი', $product->kaTranslation->name);
        $this->assertSame('satesto-konsoli', $product->kaTranslation->slug);
        $this->assertSame('test-console', $product->enTranslation->slug);

        $catalogProduct = Catalog::products('en')->firstWhere('sku', 'test-console');
        $this->assertSame('Test Console', $catalogProduct['name']);
        $this->assertSame(1299.0, $catalogProduct['price']);
    }

    public function test_english_translation_is_optional_and_old_price_must_exceed_price(): void
    {
        $this->actingAs($this->admin);

        $product = Product::query()->where('sku', 'ip16')->first();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertSchemaStateSet(['kaTranslation.name' => 'iPhone 16 Pro'])
            ->fillForm(['price' => 3500, 'old_price' => 3000])
            ->call('save')
            ->assertHasFormErrors(['old_price']);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['price' => 3500, 'old_price' => null, 'kaTranslation' => ['name' => 'iPhone 16 Pro Max']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('3500.00', $product->fresh()->price);
        $this->assertSame('iPhone 16 Pro Max', $product->kaTranslation()->first()->name);
        $this->assertSame('iPhone 16 Pro Max', Catalog::products('ka')->firstWhere('sku', 'ip16')['name']);
    }

    public function test_categories_with_children_or_products_are_not_deleted(): void
    {
        $this->actingAs($this->admin);

        $parent = Category::query()->where('code', 'gaming')->first();

        Livewire::test(EditCategory::class, ['record' => $parent->getRouteKey()])
            ->callAction('delete')
            ->assertNotified(__('admin.categories.cannot_delete'));

        $this->assertModelExists($parent);
    }

    public function test_quick_orders_appear_in_the_orders_list(): void
    {
        $this->actingAs($this->admin);

        $iphone = Product::query()->where('sku', 'ip16')->first();

        Livewire::test('quick-order', ['productId' => $iphone->id])
            ->set('name', 'ნინო')
            ->set('phone', '555 12 34 56')
            ->set('payment', 'transfer')
            ->call('submit')
            ->assertHasNoErrors();

        $quickOrder = Order::query()->where('source', 'quick')->sole();

        Livewire::test(ListOrders::class)
            ->assertCanSeeTableRecords([$quickOrder])
            ->filterTable('source', 'checkout')
            ->assertCanNotSeeTableRecords([$quickOrder]);

        Livewire::test(ViewOrder::class, ['record' => $quickOrder->getRouteKey()])
            ->assertSee(__('admin.orders.sources.quick'))
            ->assertSee(__('admin.orders.quick_note'))
            ->assertSee(PaymentMethod::labelFor('transfer'));

        $iphone->delete();
        $this->assertNull($quickOrder->items()->first()->product_id);
    }

    public function test_order_status_can_be_changed(): void
    {
        $this->actingAs($this->admin);

        $order = Order::query()->create([
            'number' => 'EL-000001', 'status' => 'new', 'first_name' => 'Nino', 'last_name' => 'Beridze', 'phone' => '555000000',
            'email' => 'nino@example.com', 'shipping_method' => 'courier', 'city' => 'tbilisi', 'address' => 'Chavchavadze 1',
            'payment_method' => 'cash', 'subtotal' => 100, 'discount' => 0, 'shipping' => 5, 'total' => 105, 'locale' => 'ka',
        ]);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertSee('EL-000001')
            ->set('status', 'call_later')
            ->call('changeStatus')
            ->assertHasNoErrors()
            ->assertNotified(__('admin.orders.status_changed'));

        $this->assertSame('call_later', $order->fresh()->status);
        $this->assertSame(
            ['type' => 'status', 'from' => 'new', 'to' => 'call_later', 'user_id' => $this->admin->id],
            $order->events()->first()->only(['type', 'from', 'to', 'user_id']),
        );

        Livewire::test(ListOrders::class)
            ->callTableAction('changeStatus', $order, ['status' => 'completed']);

        $this->assertSame(['call_later', 'completed'], [$order->events()->first()->from, $order->events()->first()->to]);
    }

    public function test_orders_are_filtered_by_status_and_can_be_deleted(): void
    {
        $this->actingAs($this->admin);

        $base = [
            'first_name' => 'Nino', 'last_name' => 'Beridze', 'phone' => '555000000', 'email' => 'nino@example.com',
            'shipping_method' => 'courier', 'city' => 'tbilisi', 'address' => 'Chavchavadze 1', 'payment_method' => 'cash',
            'subtotal' => 100, 'discount' => 0, 'shipping' => 5, 'total' => 105, 'locale' => 'ka',
        ];
        $new = Order::query()->create([...$base, 'number' => 'EL-000011', 'status' => 'new']);
        $callLater = Order::query()->create([...$base, 'number' => 'EL-000012', 'status' => 'call_later']);
        $cancelled = Order::query()->create([...$base, 'number' => 'EL-000013', 'status' => 'cancelled']);
        $new->items()->create(['name' => 'Kettle', 'unit_price' => 100, 'quantity' => 1, 'total' => 100]);
        $new->log('note', 'test');

        Livewire::test(ListOrders::class)
            ->filterTable('status', 'call_later')
            ->assertCanSeeTableRecords([$callLater])
            ->assertCanNotSeeTableRecords([$new, $cancelled])
            ->resetTableFilters()
            ->callTableAction('delete', $new)
            ->callTableBulkAction('delete', [$cancelled])
            ->assertCanNotSeeTableRecords([$new, $cancelled])
            ->filterTable('with_deleted')
            ->assertCanSeeTableRecords([$new, $cancelled, $callLater])
            ->callTableAction('restore', $cancelled)
            ->callTableAction('forceDelete', $new);

        $this->assertModelMissing($new);
        $this->assertDatabaseMissing('order_items', ['order_id' => $new->id]);
        $this->assertDatabaseMissing('order_events', ['order_id' => $new->id]);
        $this->assertNotSoftDeleted($cancelled);
        $this->assertNotSoftDeleted($callLater);

        Livewire::test(ViewOrder::class, ['record' => $callLater->getRouteKey()])
            ->callAction('delete')
            ->assertRedirect(OrderResource::getUrl());

        $this->assertSoftDeleted($callLater);

        Livewire::test(ViewOrder::class, ['record' => $callLater->getRouteKey()])
            ->assertActionVisible('restore')
            ->callAction('restore');

        $this->assertNotSoftDeleted($callLater);
        $this->assertSame(
            ['opened', 'deleted', 'restored'],
            $callLater->events()->reorder('id')->pluck('type')->all(),
        );
        $this->assertSame($this->admin->id, $callLater->events()->where('type', 'opened')->value('user_id'));

        Livewire::test(ViewOrder::class, ['record' => $callLater->getRouteKey()])
            ->assertSee(__('admin.orders.event_opened'))
            ->assertSee(__('admin.orders.event_deleted'))
            ->assertSee(__('admin.orders.event_restored'));

        $this->assertSame(1, $callLater->events()->where('type', 'opened')->count());
        $this->assertSame('ახალი შეკვეთა', OrderResource::statusOptions()['new']);
        $this->assertSame(
            ['new', 'received', 'no_answer', 'call_later', 'completed', 'cancelled'],
            array_keys(OrderResource::statusOptions()),
        );
        $this->assertSame(
            ['ნანახი შეკვეთა', 'არ პასუხობს', 'მოგვიანებით დარეკვა', 'დასრულებულია'],
            array_values(array_slice(OrderResource::statusOptions(), 1, 4)),
        );
    }

    public function test_settings_are_saved_and_shown_on_the_site(): void
    {
        $this->refreshApplicationWithLocale('ka');
        $this->actingAs($this->admin);

        Livewire::test(ManageSettings::class)
            ->assertSchemaStateSet(['site_name' => 'ELIO.GE'], 'form')
            ->fillForm(['phone' => '032 2 11 22 33', 'email' => 'hello@elio.ge'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('032 2 11 22 33', Setting::get('phone'));
        $this->assertSame('hello@elio.ge', Site::email());

        Livewire::test(ManageSettings::class)
            ->fillForm(['facebook' => 'http://facebook.com/elio'])
            ->call('save')
            ->assertHasFormErrors(['facebook']);

        Livewire::test(ManageSettings::class)
            ->fillForm(['facebook' => 'https://www.facebook.com/elio.ge', 'instagram' => 'https://www.instagram.com/elio.ge'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['facebook' => 'https://www.facebook.com/elio.ge', 'instagram' => 'https://www.instagram.com/elio.ge'], Site::socials());

        $this->get('/ka')
            ->assertOk()
            ->assertSee('href="https://www.facebook.com/elio.ge"', false)
            ->assertSee('aria-label="Instagram"', false)
            ->assertDontSee('aria-label="TikTok"', false);
    }

    public function test_logo_is_uploaded_in_settings_and_replaces_the_default_everywhere(): void
    {
        $this->refreshApplicationWithLocale('ka');
        Storage::fake('public');
        $this->actingAs($this->admin);

        Livewire::test(ManageSettings::class)
            ->fillForm(['logo' => UploadedFile::fake()->image('logo.png', 400, 120), 'logo_with_name' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = Setting::get('logo');
        Storage::disk('public')->assertExists($path);
        $this->assertStringEndsWith('.webp', $path);
        $this->assertTrue(Site::logoIncludesName());

        $this->get('/ka')
            ->assertOk()
            ->assertSee(Storage::disk('public')->url($path), false)
            ->assertSee('alt="ELIO.GE"', false);

        Livewire::test(ManageSettings::class)
            ->assertSchemaStateSet(['logo_with_name' => true], 'form')
            ->fillForm(['logo' => null])
            ->fillForm(['logo' => [UploadedFile::fake()->image('mark.png', 200, 200)], 'logo_with_name' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        Storage::disk('public')->assertMissing($path);
        $this->assertFalse(Site::logoIncludesName());
        $this->assertSame(Storage::disk('public')->url(Setting::get('logo')), Site::faviconUrl());

        Livewire::test(ManageSettings::class)
            ->fillForm(['logo' => null])
            ->fillForm(['logo' => [UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')]])
            ->call('save')
            ->assertHasFormErrors(['logo']);
    }

    public function test_admin_can_grant_access_but_not_remove_their_own(): void
    {
        $this->actingAs($this->admin);

        $customer = User::factory()->create();

        Livewire::test(ManageUsers::class)
            ->callTableAction('edit', $customer, ['is_admin' => true])
            ->assertHasNoFormErrors();

        $this->assertTrue($customer->fresh()->is_admin);

        Livewire::test(ManageUsers::class)
            ->callTableAction('edit', $this->admin, ['is_admin' => false, 'name' => 'Admin'])
            ->assertHasNoFormErrors();

        $this->assertTrue($this->admin->fresh()->is_admin);
        $this->assertSame('Admin', $this->admin->fresh()->name);
    }

    public function test_make_admin_command(): void
    {
        $user = User::factory()->create(['email' => 'staff@example.com']);

        $this->artisan('elio:admin', ['email' => 'staff@example.com'])->assertSuccessful();
        $this->assertTrue($user->fresh()->is_admin);

        $this->artisan('elio:admin', ['email' => 'staff@example.com', '--revoke' => true])->assertSuccessful();
        $this->assertFalse($user->fresh()->is_admin);

        $this->artisan('elio:admin', ['email' => 'nobody@example.com'])->assertFailed();
    }
}
