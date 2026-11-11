<?php

use App\Models\User;
use App\Services\Facebook\Pixel;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    public string $tab = 'login';

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public bool $remember = true;

    public bool $terms = false;

    public bool $resetSent = false;

    #[Locked]
    public string $returnUrl = '';

    public function mount(): void
    {
        $this->returnUrl = url()->current();
    }

    public function switchTab(string $tab): void
    {
        $this->tab = in_array($tab, ['register', 'forgot'], true) ? $tab : 'login';
        $this->reset('password', 'terms', 'resetSent');
        $this->resetErrorBag();
    }

    public function sendResetLink(): void
    {
        $this->validate(['email' => ['required', 'string', 'email']]);

        $throttleKey = 'password-reset|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                    'minutes' => ceil(RateLimiter::availableIn($throttleKey) / 60),
                ]),
            ]);
        }

        RateLimiter::hit($throttleKey, 600);

        $status = PasswordBroker::sendResetLink(['email' => Str::lower($this->email)]);

        if ($status === PasswordBroker::RESET_THROTTLED) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        $this->resetSent = true;
    }

    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($this->email).'|'.request()->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                    'minutes' => ceil(RateLimiter::availableIn($throttleKey) / 60),
                ]),
            ]);
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($throttleKey);
        $intended = session()->pull('url.intended');
        session()->regenerate();

        $this->redirect($intended ?: ($this->returnUrl ?: route('home')));
    }

    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', Password::defaults()],
            'terms' => ['accepted'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        event(new Registered($user));

        Auth::login($user, true);
        session()->regenerate();

        $pixel = app(Pixel::class);
        $pixel->flash($pixel->completeRegistration($user));

        $this->redirect($this->returnUrl ?: route('home'));
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();

        session()->invalidate();
        session()->regenerateToken();

        $this->redirect($this->returnUrl ?: route('home'));
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => __('shop.auth.full_name'),
            'email' => __('shop.auth.email'),
            'password' => __('shop.auth.password'),
            'terms' => __('shop.auth.terms'),
        ];
    }
};
?>

<div x-init="if (new URLSearchParams(window.location.search).has('login') && ! @js(auth()->check())) { $store.ui.auth = true }">
    <template x-teleport="body">
        <div x-show="$store.ui.auth" x-cloak>
            <div x-on:click="$store.ui.auth = false" class="fixed inset-0 z-[80] animate-fade bg-navy/50 backdrop-blur-sm"></div>
            <div role="dialog" aria-modal="true" class="fixed top-1/2 left-1/2 z-[81] max-h-[calc(100vh-32px)] w-[min(440px,calc(100vw-32px))] -translate-x-1/2 -translate-y-1/2 overflow-auto rounded-3xl bg-white p-7 shadow-[0_30px_80px_rgba(10,22,51,.35)]">
                <div class="mb-5 flex items-center justify-between">
                    @include('partials.logo', ['size' => 'sm'])
                    <button type="button" x-on:click="$store.ui.auth = false" aria-label="{{ __('shop.close') }}" class="grid size-9 place-items-center rounded-full bg-[#F1F5FC] text-navy hover:bg-line">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>

                @auth
                    <div class="flex flex-col items-center gap-2 py-4 text-center">
                        <span class="mb-2 grid size-[72px] place-items-center rounded-full bg-tint font-display text-[28px] font-extrabold text-brand">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                        <span class="text-xs font-bold tracking-[0.6px] text-subtle uppercase">{{ __('shop.auth.signed_in_as') }}</span>
                        <span class="text-lg font-extrabold text-navy">{{ auth()->user()->name }}</span>
                        <span class="text-sm text-muted">{{ auth()->user()->email }}</span>
                    </div>
                    <a href="{{ route('account') }}" wire:navigate x-on:click="$store.ui.auth = false" class="mt-4 grid h-[52px] w-full place-items-center rounded-xl bg-brand text-[15px] font-bold text-white hover:bg-brand-dark hover:text-white">{{ __('account.title') }}</a>
                    <button type="button" wire:click="logout" wire:loading.attr="disabled" class="mt-2.5 h-[52px] w-full rounded-xl border-[1.5px] border-line bg-white text-[15px] font-bold text-navy hover:border-sale hover:text-sale disabled:opacity-60">{{ __('shop.auth.logout') }}</button>
                @elseif ($tab === 'forgot')
                    <div class="mb-5 flex flex-col gap-2">
                        <h2 class="m-0 text-xl font-extrabold tracking-[0.3px]">{{ __('shop.auth.forgot_title') }}</h2>
                        <p class="m-0 text-sm leading-relaxed text-muted">{{ __('shop.auth.forgot_text') }}</p>
                    </div>

                    @if ($resetSent)
                        <div role="status" class="flex items-start gap-3 rounded-2xl bg-ok-soft p-4 text-sm leading-relaxed text-ok">
                            <svg class="mt-0.5 shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/></svg>
                            <span>{{ __('shop.auth.reset_sent', ['count' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire')]) }}</span>
                        </div>
                    @else
                        <form wire:submit="sendResetLink" class="flex flex-col gap-3.5" novalidate>
                            <label class="flex flex-col gap-1.5 text-[13px] font-semibold">
                                {{ __('shop.auth.email') }}
                                <input wire:model="email" name="email" type="email" autocomplete="email" placeholder="name@mail.com" @class(['h-12 rounded-xl border-[1.5px] px-3.5 text-[15px] font-normal text-navy outline-0 focus:border-brand', 'border-sale' => $errors->has('email'), 'border-line' => ! $errors->has('email')])>
                                @error('email') <span class="text-xs font-medium text-sale">{{ $message }}</span> @enderror
                            </label>
                            <button type="submit" wire:loading.attr="disabled" class="mt-1 h-[52px] rounded-xl bg-brand text-[15px] font-bold text-white hover:bg-brand-dark disabled:opacity-60">{{ __('shop.auth.send_link') }}</button>
                        </form>
                    @endif

                    <button type="button" wire:click="switchTab('login')" class="mt-4 flex h-11 w-full items-center justify-center gap-1.5 rounded-xl text-sm font-bold text-navy hover:bg-[#F1F5FC]">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 6-6 6 6 6"/></svg>
                        {{ __('shop.auth.back_to_login') }}
                    </button>
                @else
                    <div class="mb-[22px] grid grid-cols-2 gap-1 rounded-xl bg-[#F1F5FC] p-1">
                        @foreach (['login', 'register'] as $tabName)
                            <button
                                type="button"
                                wire:click="switchTab('{{ $tabName }}')"
                                @class([
                                    'h-10 rounded-[9px] text-sm font-bold',
                                    'bg-white text-navy shadow-[0_1px_4px_rgba(10,22,51,.1)]' => $tab === $tabName,
                                    'text-muted' => $tab !== $tabName,
                                ])
                            >{{ __("shop.auth.$tabName") }}</button>
                        @endforeach
                    </div>

                    <form wire:submit="{{ $tab === 'register' ? 'register' : 'login' }}" class="flex flex-col gap-3.5" novalidate>
                        @if ($tab === 'register')
                            <label class="flex flex-col gap-1.5 text-[13px] font-semibold">
                                {{ __('shop.auth.full_name') }}
                                <input wire:model="name" name="name" autocomplete="name" placeholder="{{ __('shop.auth.name_placeholder') }}" @class(['h-12 rounded-xl border-[1.5px] px-3.5 text-[15px] font-normal text-navy outline-0 focus:border-brand', 'border-sale' => $errors->has('name'), 'border-line' => ! $errors->has('name')])>
                                @error('name') <span class="text-xs font-medium text-sale">{{ $message }}</span> @enderror
                            </label>
                        @endif

                        <label class="flex flex-col gap-1.5 text-[13px] font-semibold">
                            {{ __('shop.auth.email') }}
                            <input wire:model="email" name="email" type="email" autocomplete="email" placeholder="name@mail.com" @class(['h-12 rounded-xl border-[1.5px] px-3.5 text-[15px] font-normal text-navy outline-0 focus:border-brand', 'border-sale' => $errors->has('email'), 'border-line' => ! $errors->has('email')])>
                            @error('email') <span class="text-xs font-medium text-sale">{{ $message }}</span> @enderror
                        </label>

                        <label class="flex flex-col gap-1.5 text-[13px] font-semibold" x-data="{ visible: false }">
                            {{ __('shop.auth.password') }}
                            <span class="relative flex">
                                <input wire:model="password" name="password" :type="visible ? 'text' : 'password'" autocomplete="{{ $tab === 'register' ? 'new-password' : 'current-password' }}" placeholder="{{ $tab === 'register' ? __('shop.auth.password_placeholder') : '••••••••' }}" @class(['h-12 w-full rounded-xl border-[1.5px] pr-20 pl-3.5 text-[15px] font-normal text-navy outline-0 focus:border-brand', 'border-sale' => $errors->has('password'), 'border-line' => ! $errors->has('password')])>
                                <button type="button" x-on:click="visible = ! visible" class="absolute inset-y-0 right-3 my-auto h-8 px-1 text-xs font-bold text-brand" x-text="visible ? @js(__('shop.auth.hide')) : @js(__('shop.auth.show'))"></button>
                            </span>
                            @error('password') <span class="text-xs font-medium text-sale">{{ $message }}</span> @enderror
                        </label>

                        @if ($tab === 'register')
                            <label class="flex flex-col gap-1">
                                <span class="flex items-start gap-2.5 text-[13px] leading-normal text-muted">
                                    <input wire:model="terms" type="checkbox" class="mt-px size-[18px] shrink-0 accent-brand">
                                    {{ __('shop.auth.agree') }}
                                </span>
                                @error('terms') <span class="text-xs font-medium text-sale">{{ $message }}</span> @enderror
                            </label>
                        @else
                            <div class="flex items-center justify-between gap-3">
                                <label class="flex items-center gap-2.5 text-[13px] text-muted">
                                    <input wire:model="remember" type="checkbox" class="size-[18px] accent-brand">
                                    {{ __('shop.auth.remember') }}
                                </label>
                                <button type="button" wire:click="switchTab('forgot')" class="text-[13px] font-bold text-brand hover:text-brand-dark">{{ __('shop.auth.forgot') }}</button>
                            </div>
                        @endif

                        <button type="submit" wire:loading.attr="disabled" class="mt-1 h-[52px] rounded-xl bg-brand text-[15px] font-bold text-white hover:bg-brand-dark disabled:opacity-60">
                            {{ $tab === 'register' ? __('shop.auth.create_account') : __('shop.auth.login') }}
                        </button>
                    </form>
                @endauth
            </div>
        </div>
    </template>
</div>
