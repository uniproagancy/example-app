<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_user_can_register(): void
    {
        Livewire::test('auth-modal')
            ->call('switchTab', 'register')
            ->set('name', 'ნინო ბერიძე')
            ->set('email', 'nino@example.com')
            ->set('password', 'secret-password')
            ->set('terms', true)
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'nino@example.com', 'name' => 'ნინო ბერიძე']);
    }

    public function test_registration_requires_valid_data(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        Livewire::test('auth-modal')
            ->call('switchTab', 'register')
            ->set('name', '')
            ->set('email', 'taken@example.com')
            ->set('password', 'short')
            ->set('terms', false)
            ->call('register')
            ->assertHasErrors(['name' => 'required', 'email' => 'unique', 'password', 'terms' => 'accepted']);

        $this->assertGuest();
    }

    public function test_user_can_log_in(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        Livewire::test('auth-modal')
            ->set('email', $user->email)
            ->set('password', 'secret-password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        Livewire::test('auth-modal')
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $component = Livewire::test('auth-modal')->set('email', $user->email);

        foreach (range(1, 5) as $attempt) {
            $component->set('password', 'wrong-password')->call('login');
        }

        $component->set('password', 'secret-password')->call('login')->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('auth-modal')
            ->assertSee($user->email)
            ->call('logout')
            ->assertRedirect();

        $this->assertFalse(Auth::check());
    }

    public function test_validation_messages_are_translated(): void
    {
        app()->setLocale('ka');

        Livewire::test('auth-modal')
            ->set('email', '')
            ->set('password', '')
            ->call('login')
            ->assertSee('ველი „ელ-ფოსტა“ სავალდებულოა.');
    }
}
