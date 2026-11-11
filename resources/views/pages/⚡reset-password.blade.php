<?php

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function resetPassword(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $resetUser = null;

        $status = PasswordBroker::reset(
            [
                'email' => Str::lower($this->email),
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function (User $user, string $password) use (&$resetUser): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                $resetUser = $user;
            },
        );

        if ($status !== PasswordBroker::PASSWORD_RESET || ! $resetUser) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        Auth::login($resetUser, true);
        session()->regenerate();
        session()->flash('toast', __('shop.auth.reset.done'));

        $this->redirect(route('account'));
    }

    protected function validationAttributes(): array
    {
        return [
            'email' => __('shop.auth.email'),
            'password' => __('shop.auth.reset.new_password'),
        ];
    }

    public function render()
    {
        return $this->view()->title(__('shop.auth.forgot_title'));
    }
};
?>

<main class="mx-auto flex max-w-[1480px] flex-col items-center px-4 pt-10 sm:px-6 sm:pt-16 lg:px-8">
    <div class="w-full max-w-[460px] rounded-[28px] border border-line-mid bg-white p-7 shadow-[0_24px_60px_-24px_rgba(10,22,51,.25)] sm:p-9">
        <span class="mb-5 grid size-14 place-items-center rounded-2xl bg-tint text-brand">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2.5"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><circle cx="12" cy="15.5" r="1.4" fill="currentColor" stroke="none"/></svg>
        </span>
        <h1 class="m-0 text-2xl font-extrabold tracking-[0.3px]">{{ __('shop.auth.reset.title') }}</h1>
        <p class="mt-2 mb-6 text-sm leading-relaxed text-muted">{{ __('shop.auth.reset.text') }}</p>

        <form wire:submit="resetPassword" class="flex flex-col gap-3.5" novalidate>
            <label class="flex flex-col gap-1.5 text-[13px] font-semibold">
                {{ __('shop.auth.email') }}
                <input wire:model="email" name="email" type="email" autocomplete="email" @class(['h-12 rounded-xl border-[1.5px] px-3.5 text-[15px] font-normal text-navy outline-0 focus:border-brand', 'border-sale' => $errors->has('email'), 'border-line' => ! $errors->has('email')])>
                @error('email') <span class="text-xs font-medium text-sale">{{ $message }}</span> @enderror
            </label>

            <label class="flex flex-col gap-1.5 text-[13px] font-semibold" x-data="{ visible: false }">
                {{ __('shop.auth.reset.new_password') }}
                <span class="relative flex">
                    <input wire:model="password" name="password" :type="visible ? 'text' : 'password'" autocomplete="new-password" placeholder="{{ __('shop.auth.password_placeholder') }}" @class(['h-12 w-full rounded-xl border-[1.5px] pr-20 pl-3.5 text-[15px] font-normal text-navy outline-0 focus:border-brand', 'border-sale' => $errors->has('password'), 'border-line' => ! $errors->has('password')])>
                    <button type="button" x-on:click="visible = ! visible" class="absolute inset-y-0 right-3 my-auto h-8 px-1 text-xs font-bold text-brand" x-text="visible ? @js(__('shop.auth.hide')) : @js(__('shop.auth.show'))"></button>
                </span>
                @error('password') <span class="text-xs font-medium text-sale">{{ $message }}</span> @enderror
            </label>

            <label class="flex flex-col gap-1.5 text-[13px] font-semibold">
                {{ __('shop.auth.reset.confirm_password') }}
                <input wire:model="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="h-12 rounded-xl border-[1.5px] border-line px-3.5 text-[15px] font-normal text-navy outline-0 focus:border-brand">
            </label>

            <button type="submit" wire:loading.attr="disabled" class="mt-1 h-[52px] rounded-xl bg-brand text-[15px] font-bold text-white hover:bg-brand-dark disabled:opacity-60">{{ __('shop.auth.reset.submit') }}</button>
        </form>
    </div>
</main>
