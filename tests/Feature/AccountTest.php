<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Catalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $this->get('/ka/account')->assertRedirect(url('/ka?login=1'));
    }

    public function test_account_page_renders_in_each_language(): void
    {
        $user = User::factory()->create(['name' => 'ნინო ბერიძე']);

        foreach (['ka' => '/ka/account', 'en' => '/en/account'] as $locale => $uri) {
            $this->refreshApplicationWithLocale($locale);

            $this->actingAs($user)
                ->get($uri)
                ->assertOk()
                ->assertSee(__('account.hello', ['name' => 'ნინო'], $locale))
                ->assertSee($user->email);
        }
    }

    public function test_orders_tab_shows_each_ordered_product_with_its_photo_and_link(): void
    {
        $this->refreshApplicationWithLocale('ka');
        $user = User::factory()->create();
        $product = Product::query()->where('sku', 'ip16')->sole();
        $product->images()->create(['path' => 'products/ip16/front.jpg', 'position' => 0]);
        Catalog::flush();
        $iphone = Catalog::find($product->id);

        $order = Order::query()->create([
            'number' => 'EL-111222', 'user_id' => $user->id, 'status' => 'new', 'source' => 'checkout', 'first_name' => 'Nino', 'last_name' => 'Beridze',
            'phone' => '555123456', 'email' => 'nino@example.com', 'shipping_method' => 'courier', 'city' => 'tbilisi', 'address' => 'Chavchavadze 1',
            'payment_method' => 'cash', 'payment_status' => Order::PAYMENT_PENDING, 'subtotal' => 1900, 'discount' => 0, 'shipping' => 0, 'total' => 1900, 'locale' => 'ka',
        ]);
        $order->items()->create(['product_id' => $iphone['id'], 'name' => 'iPhone 16 Pro', 'unit_price' => 950, 'quantity' => 2, 'total' => 1900]);
        $order->items()->create(['product_id' => null, 'name' => 'Removed item', 'unit_price' => 10, 'quantity' => 1, 'total' => 10]);

        $html = $this->actingAs($user)->get('/ka/account?tab=orders')->assertOk()->getContent();

        $this->assertStringContainsString('href="'.$iphone['url'].'"', $html);
        $this->assertStringContainsString('src="'.$iphone['images'][0].'"', $html);
        $this->assertStringContainsString('iPhone 16 Pro', $html);
        $this->assertStringContainsString('Removed item', $html);
    }

    public function test_profile_can_be_updated(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::account', ['tab' => 'profile'])
            ->set('name', 'გიორგი მაისურაძე')
            ->set('phone', '+995 555 12 34 56')
            ->set('email', 'giorgi@example.com')
            ->call('saveProfile')
            ->assertHasNoErrors()
            ->assertSet('profileSaved', true);

        $user->refresh();
        $this->assertSame('გიორგი მაისურაძე', $user->name);
        $this->assertSame('+995 555 12 34 56', $user->phone);
        $this->assertSame('giorgi@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_profile_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::account')
            ->set('email', 'taken@example.com')
            ->set('phone', 'not a phone!')
            ->call('saveProfile')
            ->assertHasErrors(['email' => 'unique', 'phone' => 'regex']);
    }

    public function test_password_can_be_changed_with_current_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        Livewire::actingAs($user)
            ->test('pages::account')
            ->set('current_password', 'wrong-password')
            ->set('password', 'new-secret-password')
            ->call('updatePassword')
            ->assertHasErrors(['current_password' => 'current_password'])
            ->set('current_password', 'old-password')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertSet('passwordUpdated', true)
            ->assertSet('current_password', '');

        $this->assertTrue(Hash::check('new-secret-password', $user->refresh()->password));
    }

    public function test_notification_preferences_toggle(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->wantsNotification('promo'));

        Livewire::actingAs($user)
            ->test('pages::account')
            ->call('toggleNotification', 'promo')
            ->call('toggleNotification', 'sms')
            ->call('toggleNotification', 'unknown');

        $user->refresh();
        $this->assertTrue($user->wantsNotification('promo'));
        $this->assertFalse($user->wantsNotification('sms'));
        $this->assertTrue($user->wantsNotification('mail'));
    }

    public function test_addresses_can_be_managed(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test('pages::account', ['tab' => 'addresses'])
            ->set('addressTitle', 'სახლი')
            ->set('addressLine', 'თბილისი, ჭავჭავაძის გამზ. 21')
            ->call('addAddress')
            ->assertHasNoErrors()
            ->set('addressTitle', 'ოფისი')
            ->set('addressLine', 'თბილისი, ვაჟა-ფშაველას 71')
            ->call('addAddress');

        [$home, $office] = $user->addresses()->orderBy('id')->get();
        $this->assertTrue($home->is_default);
        $this->assertFalse($office->is_default);

        $component->call('makeDefaultAddress', $office->id);
        $this->assertTrue($office->refresh()->is_default);
        $this->assertFalse($home->refresh()->is_default);

        $component->call('deleteAddress', $office->id);
        $this->assertTrue($home->refresh()->is_default);
        $this->assertSame(1, $user->addresses()->count());
    }

    public function test_users_cannot_touch_other_users_addresses(): void
    {
        $owner = User::factory()->create();
        $address = $owner->addresses()->create(['title' => 'სახლი', 'line' => 'თბილისი', 'is_default' => true]);

        Livewire::actingAs(User::factory()->create())
            ->test('pages::account')
            ->call('deleteAddress', $address->id)
            ->assertNotFound();

        $this->assertDatabaseHas('addresses', ['id' => $address->id]);
    }

    public function test_user_can_log_out_from_account(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::account')
            ->call('logout')
            ->assertRedirect();

        $this->assertGuest();
    }
}
