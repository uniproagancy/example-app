<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_reset_link_is_sent_in_the_current_language(): void
    {
        $this->refreshApplicationWithLocale('ka');
        Notification::fake();

        $user = User::factory()->create(['name' => 'ნინო ბერიძე', 'email' => 'nino@example.com']);

        Livewire::test('auth-modal')
            ->call('switchTab', 'forgot')
            ->set('email', 'Nino@Example.com')
            ->call('sendResetLink')
            ->assertHasNoErrors()
            ->assertSet('resetSent', true)
            ->assertSee(__('shop.auth.reset_sent', ['count' => 60], 'ka'));

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
            $mail = $notification->toMail($user);

            return $mail->subject === 'პაროლის აღდგენა — ELIO.GE'
                && $mail->greeting === 'გამარჯობა, ნინო!'
                && str_starts_with($mail->actionUrl, url('/ka/reset-password/'.$notification->token))
                && str_contains($mail->actionUrl, 'email=nino%40example.com');
        });
    }

    public function test_unknown_email_gets_the_same_answer_without_mail(): void
    {
        Notification::fake();

        Livewire::test('auth-modal')
            ->call('switchTab', 'forgot')
            ->set('email', 'nobody@example.com')
            ->call('sendResetLink')
            ->assertHasNoErrors()
            ->assertSet('resetSent', true);

        Notification::assertNothingSent();
    }

    public function test_reset_link_requests_are_rate_limited(): void
    {
        Notification::fake();

        $component = Livewire::test('auth-modal')->call('switchTab', 'forgot');

        foreach (range(1, 5) as $attempt) {
            $component->set('email', "user{$attempt}@example.com")->call('sendResetLink')->assertHasNoErrors();
        }

        $component->set('email', 'user6@example.com')->call('sendResetLink')->assertHasErrors('email');
    }

    public function test_user_can_set_a_new_password_and_is_signed_in(): void
    {
        $this->refreshApplicationWithLocale('en');

        $user = User::factory()->create(['email' => 'nino@example.com']);
        $token = Password::createToken($user);

        $this->get('/en/reset-password/'.$token.'?email=nino@example.com')
            ->assertOk()
            ->assertSee(__('shop.auth.reset.title', [], 'en'));

        Livewire::withQueryParams(['email' => 'nino@example.com'])
            ->test('pages::reset-password', ['token' => $token])
            ->assertSet('email', 'nino@example.com')
            ->set('password', 'brand-new-secret')
            ->set('password_confirmation', 'brand-new-secret')
            ->call('resetPassword')
            ->assertHasNoErrors()
            ->assertRedirect(url('/en/account'));

        $this->assertTrue(Hash::check('brand-new-secret', $user->fresh()->password));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(__('shop.auth.reset.done', [], 'en'), session('toast'));
    }

    public function test_invalid_token_and_mismatched_passwords_are_rejected(): void
    {
        $user = User::factory()->create(['email' => 'nino@example.com']);

        Livewire::withQueryParams(['email' => 'nino@example.com'])
            ->test('pages::reset-password', ['token' => 'wrong-token'])
            ->set('password', 'brand-new-secret')
            ->set('password_confirmation', 'something-else')
            ->call('resetPassword')
            ->assertHasErrors('password')
            ->set('password_confirmation', 'brand-new-secret')
            ->call('resetPassword')
            ->assertHasErrors('email');

        $this->assertFalse(Hash::check('brand-new-secret', $user->fresh()->password));
        $this->assertGuest();
    }

    public function test_login_form_links_to_password_reset(): void
    {
        Livewire::test('auth-modal')->assertSee(__('shop.auth.forgot'));
    }
}
