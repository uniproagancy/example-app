<?php

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PromoCode;
use App\Services\Facebook\Pixel;
use App\Services\Orders\OrderNotifier;
use App\Services\Payments\BankHandoff;
use App\Support\Cart;
use App\Support\Catalog;
use App\Support\Site;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public string $firstName = '';

    public string $lastName = '';

    public string $phone = '';

    public string $email = '';

    public string $shipping = 'courier';

    public string $city = 'tbilisi';

    public string $address = '';

    public string $apartment = '';

    public string $note = '';

    public string $payment = '';

    public string $promoInput = '';

    #[Locked]
    public ?string $promoCode = null;

    public ?string $promoMessage = null;

    public bool $promoFailed = false;

    public bool $agree = true;

    #[Locked]
    public ?array $placed = null;

    public function mount(): void
    {
        if ($user = auth()->user()) {
            [$this->firstName, $this->lastName] = array_pad(explode(' ', trim($user->name), 2), 2, '');
            $this->phone = (string) $user->phone;
            $this->email = $user->email;
            $this->address = (string) $user->addresses()->first()?->line;
        }

        $this->payment = (string) $this->methods->first()?->code;

        $this->trackCheckoutStarted();
    }

    protected function trackCheckoutStarted(): void
    {
        $lines = $this->lines;

        if ($lines === []) {
            return;
        }

        $this->dispatch('pixel', app(Pixel::class)->initiateCheckout(
            array_map(fn (array $line) => ['id' => $line['id'], 'qty' => $line['quantity'], 'price' => $line['price']], $lines),
            (float) $this->totals['subtotal'] + (float) $this->totals['shipping'],
        ));
    }

    #[Computed]
    public function methods(): Collection
    {
        return PaymentMethod::forCheckout((float) $this->totals['total']);
    }

    public function selectedMethod(): ?PaymentMethod
    {
        return $this->methods->firstWhere('code', $this->payment);
    }

    #[On('cart-updated')]
    public function syncCart(): void
    {
        unset($this->lines, $this->totals, $this->methods);
    }

    #[Computed]
    public function lines(): array
    {
        return Cart::lines(Cart::items());
    }

    #[Computed]
    public function totals(): array
    {
        $subtotal = (float) collect($this->lines)->sum('subtotal');
        $promo = $this->promoCode ? PromoCode::findUsable($this->promoCode, $subtotal) : null;
        $discount = $promo ? $promo->discountFor($subtotal) : 0;
        $shipping = Cart::shippingFor($subtotal);
        $total = max(0, $subtotal - $discount + $shipping);

        return [
            'count' => (int) collect($this->lines)->sum('quantity'),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'promo' => $promo?->code,
            'shipping' => $shipping,
            'total' => $total,
        ];
    }

    public function increment(string $key): void
    {
        $this->changeQuantity($key, 1);
    }

    public function decrement(string $key): void
    {
        $this->changeQuantity($key, -1);
    }

    public function remove(string $key): void
    {
        $items = Cart::items();
        unset($items[$key]);
        $this->saveCart($items);
    }

    public function choose(string $field, string|int $value): void
    {
        $allowed = [
            'shipping' => Order::SHIPPING_METHODS,
            'payment' => $this->methods->pluck('code')->all(),
        ];

        if (isset($allowed[$field]) && in_array($value, $allowed[$field], true)) {
            $this->{$field} = $value;
            unset($this->totals, $this->methods);
        }
    }

    public function applyPromo(): void
    {
        $code = mb_strtoupper(trim($this->promoInput));
        $this->promoFailed = true;

        if ($code === '') {
            $this->promoMessage = __('checkout.promo_empty');

            return;
        }

        $promo = PromoCode::findUsable($code, $this->totals['subtotal']);

        if (! $promo) {
            $this->promoCode = null;
            $this->promoMessage = __('checkout.promo_invalid');
            unset($this->totals);

            return;
        }

        $this->promoCode = $promo->code;
        $this->promoInput = $promo->code;
        $this->promoFailed = false;
        $this->promoMessage = __('checkout.promo_applied', ['percent' => $promo->percent]);
        unset($this->totals);
    }

    public function removePromo(): void
    {
        $this->promoCode = null;
        $this->promoInput = '';
        $this->promoMessage = null;
        unset($this->totals);
    }

    public function placeOrder(): void
    {
        $this->validate();

        $lines = $this->lines;

        if ($lines === []) {
            throw ValidationException::withMessages(['cart' => __('checkout.empty')]);
        }

        $throttleKey = 'checkout:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'cart' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        RateLimiter::hit($throttleKey, 600);

        $totals = $this->totals;

        $order = DB::transaction(function () use ($lines, $totals) {
            $order = Order::query()->create([
                'number' => Order::generateNumber(),
                'user_id' => auth()->user()?->id,
                'first_name' => trim($this->firstName),
                'last_name' => trim($this->lastName),
                'phone' => trim($this->phone),
                'email' => mb_strtolower(trim($this->email)),
                'shipping_method' => $this->shipping,
                'city' => $this->city,
                'address' => trim($this->address),
                'apartment' => trim($this->apartment) ?: null,
                'note' => trim($this->note) ?: null,
                'payment_method' => $this->payment,
                'payment_status' => Order::PAYMENT_PENDING,
                'promo_code' => $totals['promo'],
                'subtotal' => $totals['subtotal'],
                'discount' => $totals['discount'],
                'shipping' => $totals['shipping'],
                'total' => $totals['total'],
                'locale' => app()->getLocale(),
            ]);

            $order->items()->createMany(collect($lines)->map(fn (array $line) => [
                'product_id' => $line['id'],
                'name' => $line['name'],
                'options' => $line['options'] ?: null,
                'unit_price' => $line['price'],
                'quantity' => $line['quantity'],
                'total' => $line['subtotal'],
            ])->all());

            return $order;
        });

        Cart::clear();
        $this->dispatch('cart-refresh');

        app(OrderNotifier::class)->placed($order);
        app(Pixel::class)->snapshotForOrder($order);

        $order->rememberInSession();
        $step = app(BankHandoff::class)->begin($order);

        if ($step['type'] === 'redirect') {
            $this->redirect($step['url']);

            return;
        }

        if ($step['type'] !== 'none') {
            if ($step['type'] === 'error') {
                session()->flash('payment_error', $step['message']);
            }

            $this->redirect($order->pageUrl().($step['type'] === 'calculator' ? '?pay=1' : ''));

            return;
        }

        $this->placed = [
            'number' => $order->number,
            'shipping' => __("checkout.methods.{$this->shipping}.title").' · '.__("checkout.cities.{$this->city}"),
            'payment' => $order->paymentLabel(),
            'total' => Catalog::money($order->total),
            'url' => $order->pageUrl(),
        ];

        $this->js('window.scrollTo({ top: 0 })');
    }

    protected function rules(): array
    {
        return [
            'firstName' => ['required', 'string', 'max:60'],
            'lastName' => ['required', 'string', 'max:60'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9\s\-()]{9,20}$/'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'shipping' => ['required', Rule::in(Order::SHIPPING_METHODS)],
            'city' => ['required', Rule::in(Order::CITIES)],
            'address' => ['required', 'string', 'max:255'],
            'apartment' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
            'payment' => ['required', Rule::in($this->methods->pluck('code')->all())],
            'agree' => ['accepted'],
        ];
    }

    protected function messages(): array
    {
        return [
            'agree.accepted' => __('checkout.agree_error'),
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'firstName' => __('checkout.first_name'),
            'lastName' => __('checkout.last_name'),
            'phone' => __('checkout.phone'),
            'email' => __('checkout.email'),
            'city' => __('checkout.city'),
            'address' => __('checkout.address'),
        ];
    }

    protected function changeQuantity(string $key, int $delta): void
    {
        $items = Cart::items();

        if (isset($items[$key])) {
            $items[$key] = max(1, $items[$key] + $delta);
            $this->saveCart($items);
        }
    }

    protected function saveCart(array $items): void
    {
        Cart::store($items);
        unset($this->lines, $this->totals);
        $this->dispatch('cart-refresh');
    }

    public function render()
    {
        return $this->view()->title(__('checkout.title'));
    }
};
?>

@php
    $chosenMethod = $this->selectedMethod();
    $field = 'h-[50px] w-full min-w-0 rounded-[14px] border-[1.5px] bg-white px-4 text-[15px] font-medium text-navy outline-none transition-colors focus:border-brand';
    $card = 'rounded-3xl border border-line bg-white p-5 sm:px-[34px] sm:py-[30px]';
    $radio = fn (bool $on) => $on ? 'border-brand bg-[#F6F9FF]' : 'border-line bg-white hover:border-brand';
@endphp

<div>
    @if ($placed)
        <main class="mx-auto max-w-[640px] px-4 py-12 sm:px-8 sm:py-16">
            <div class="flex flex-col items-center gap-3.5 rounded-[28px] border border-line bg-white p-6 text-center sm:p-10">
                <span class="grid size-[76px] place-items-center rounded-full bg-[#E7F7EE] text-[#14804A] shadow-[0_0_0_10px_rgba(31,184,102,.08)]">
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg>
                </span>
                <h1 class="m-0 mt-2 text-[22px] font-extrabold tracking-[0.3px] sm:text-[26px]">{{ __('checkout.done_title') }}</h1>
                <p class="m-0 text-sm leading-relaxed text-muted">{{ __('checkout.done_text', ['number' => '#'.$placed['number']]) }}</p>
                <div class="mt-2.5 flex w-full flex-col gap-2.5 rounded-[18px] bg-field px-5 py-[18px] text-left text-sm">
                    <div class="flex justify-between gap-3"><span class="text-muted">{{ __('checkout.done_shipping') }}</span><span class="text-right font-bold">{{ $placed['shipping'] }}</span></div>
                    <div class="flex justify-between gap-3"><span class="text-muted">{{ __('checkout.done_payment') }}</span><span class="text-right font-bold">{{ $placed['payment'] }}</span></div>
                    <div class="flex justify-between gap-3"><span class="text-muted">{{ __('checkout.done_total') }}</span><span class="font-display font-extrabold">{{ $placed['total'] }}</span></div>
                </div>
                <div class="mt-2.5 flex flex-wrap justify-center gap-2.5">
                    <a href="{{ $placed['url'] }}" class="flex h-12 items-center rounded-full bg-brand px-6 text-sm font-bold text-white hover:bg-navy hover:text-white">{{ __('checkout.view_order') }}</a>
                    <a href="{{ route('home') }}" wire:navigate class="flex h-12 items-center rounded-full bg-navy px-6 text-sm font-bold text-white hover:bg-brand hover:text-white">{{ __('checkout.back_home') }}</a>
                    @auth
                        <a href="{{ route('account', ['tab' => 'orders']) }}" wire:navigate class="flex h-12 items-center rounded-full border-[1.5px] border-line-mid bg-white px-[22px] text-sm font-bold text-navy hover:border-brand hover:text-navy">{{ __('checkout.my_orders') }}</a>
                    @endauth
                </div>
            </div>
        </main>
    @else
        <main class="mx-auto max-w-[1480px] px-4 pt-6 pb-16 sm:px-6 sm:pt-7 lg:px-8">
            <div class="mb-[22px] flex flex-wrap items-end justify-between gap-4">
                <div class="flex flex-col gap-1.5">
                    <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-1.5 text-[13px] font-semibold text-muted hover:text-brand">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m15 6-6 6 6 6"/></svg>
                        {{ __('checkout.continue') }}
                    </a>
                    <h1 class="m-0 text-2xl font-extrabold tracking-[0.3px] sm:text-[32px]">{{ __('checkout.title') }}</h1>
                </div>
                @guest
                    <span class="text-[13px] text-muted">
                        {{ __('checkout.have_account') }}
                        <button type="button" x-on:click="$store.ui.auth = true" class="p-0 text-[13px] font-bold text-brand">{{ __('checkout.login') }}</button>
                        — {{ __('checkout.login_hint') }}
                    </span>
                @endguest
            </div>

            <form wire:submit="placeOrder" class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_460px] lg:gap-8">
                <div class="flex min-w-0 flex-col gap-4">
                    <section class="{{ $card }}">
                        <h2 class="m-0 mb-5 font-sans text-lg font-extrabold normal-case">{{ __('checkout.contact') }}</h2>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @foreach ([
                                ['firstName', 'text', __('checkout.first_name_placeholder'), 'given-name'],
                                ['lastName', 'text', __('checkout.last_name_placeholder'), 'family-name'],
                                ['phone', 'tel', '+995 5__ __ __ __', 'tel'],
                                ['email', 'email', 'name@mail.ge', 'email'],
                            ] as [$name, $type, $placeholder, $autocomplete])
                                <label class="flex min-w-0 flex-col gap-1.5 text-xs font-bold text-[#3A4A68]">
                                    {{ __('checkout.'.str($name)->snake()) }}
                                    <input wire:model="{{ $name }}" type="{{ $type }}" autocomplete="{{ $autocomplete }}" placeholder="{{ $placeholder }}" @class([$field, 'border-sale' => $errors->has($name), 'border-[#E3E9F3]' => ! $errors->has($name)])>
                                    @error($name)<span class="text-xs font-semibold text-sale">{{ $message }}</span>@enderror
                                </label>
                            @endforeach
                        </div>
                    </section>

                    <section class="{{ $card }}">
                        <div class="flex flex-col gap-3.5">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-[260px_minmax(0,1fr)]">
                                <label class="flex min-w-0 flex-col gap-1.5 text-xs font-bold text-[#3A4A68]">
                                    {{ __('checkout.city') }}
                                    <select wire:model="city" class="{{ $field }} border-[#E3E9F3] px-3.5">
                                        @foreach (App\Models\Order::CITIES as $cityKey)
                                            <option value="{{ $cityKey }}">{{ __("checkout.cities.$cityKey") }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="flex min-w-0 flex-col gap-1.5 text-xs font-bold text-[#3A4A68]">
                                    {{ __('checkout.address') }}
                                    <input wire:model="address" autocomplete="street-address" placeholder="{{ __('checkout.address_placeholder') }}" @class([$field, 'border-sale' => $errors->has('address'), 'border-[#E3E9F3]' => ! $errors->has('address')])>
                                    @error('address')<span class="text-xs font-semibold text-sale">{{ $message }}</span>@enderror
                                </label>
                            </div>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <input wire:model="apartment" aria-label="{{ __('checkout.apartment_placeholder') }}" placeholder="{{ __('checkout.apartment_placeholder') }}" class="{{ $field }} border-[#E3E9F3] text-sm">
                                <input wire:model="note" aria-label="{{ __('checkout.note_placeholder') }}" placeholder="{{ __('checkout.note_placeholder') }}" class="{{ $field }} border-[#E3E9F3] text-sm">
                            </div>
                        </div>
                    </section>

                    <section class="{{ $card }}">
                        <h2 class="m-0 mb-5 font-sans text-lg font-extrabold normal-case">{{ __('checkout.payment') }}</h2>
                        <div class="flex flex-col gap-2.5">
                            @forelse ($this->methods as $method)
                                <button type="button" wire:key="method-{{ $method->code }}" wire:click="choose('payment', '{{ $method->code }}')" class="flex w-full items-center gap-3.5 rounded-2xl border-[1.5px] p-4 text-left text-navy transition-colors {{ $radio($payment === $method->code) }}">
                                    <span class="grid size-5 shrink-0 place-items-center rounded-full border-2 {{ $payment === $method->code ? 'border-brand' : 'border-[#C9D3E3]' }}">
                                        <span class="size-2.5 rounded-full bg-brand {{ $payment === $method->code ? '' : 'opacity-0' }}"></span>
                                    </span>
                                    <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                        <span class="text-sm font-extrabold">{{ $method->name }}</span>
                                        @if ($method->note)
                                            <span class="text-xs text-muted">{{ $method->note }}</span>
                                        @endif
                                    </span>
                                    @if ($logo = $method->logoUrl())
                                        <img src="{{ $logo }}" alt="" class="h-7 max-w-[88px] shrink-0 object-contain" loading="lazy">
                                    @endif
                                </button>
                            @empty
                                <p class="m-0 text-sm text-muted">{{ __('checkout.no_methods') }}</p>
                            @endforelse
                            @error('payment')<span class="text-xs font-semibold text-sale">{{ $message }}</span>@enderror
                            @if ($chosenMethod?->isPayableOnline())
                                <span class="text-xs text-muted">{{ __('checkout.bank_redirect') }}</span>
                            @endif
                        </div>
                    </section>
                </div>

                <aside class="flex min-w-0 flex-col gap-3.5 lg:sticky lg:top-[120px]">
                    <div class="flex flex-col gap-4 rounded-3xl border border-line bg-white px-[22px] pt-[22px] pb-6 shadow-[0_30px_60px_-44px_rgba(10,22,51,.3)]">
                        <div class="flex items-center justify-between">
                            <span class="text-base font-extrabold">{{ __('checkout.summary') }}</span>
                            <span class="rounded-full bg-[#F3F5F9] px-2.5 py-1 text-xs font-bold text-muted">{{ __('checkout.items_count', ['count' => $this->totals['count']]) }}</span>
                        </div>

                        <div class="flex flex-col">
                            @forelse ($this->lines as $line)
                                <div wire:key="checkout-line-{{ $line['key'] }}" class="grid grid-cols-[60px_minmax(0,1fr)_auto] items-center gap-3 border-t border-line-soft py-3">
                                    <a href="{{ $line['url'] }}" wire:navigate class="grid size-[60px] place-items-center overflow-hidden rounded-[14px] bg-[#F3F6FB] text-brand hover:text-brand">
                                        @if ($line['image'])
                                            <img src="{{ $line['image'] }}" alt="" class="size-full object-contain">
                                        @else
                                            @include('partials.category-icon', ['icon' => $line['icon'], 'stroke' => 1.8, 'class' => 'size-[22px]'])
                                        @endif
                                    </a>
                                    <div class="flex min-w-0 flex-col gap-1.5">
                                        <span class="text-[13px] leading-[1.35] font-bold">{{ $line['name'] }}@if ($line['variant'])<span class="block text-xs font-medium text-subtle">{{ $line['variant'] }}</span>@endif</span>
                                        <div class="flex items-center gap-0.5">
                                            <button type="button" wire:click="decrement('{{ $line['key'] }}')" aria-label="{{ __('checkout.less') }}" class="size-[26px] rounded-lg bg-[#F3F5F9] text-sm text-navy">−</button>
                                            <span class="min-w-6 text-center font-display text-[13px] font-extrabold">{{ $line['quantity'] }}</span>
                                            <button type="button" wire:click="increment('{{ $line['key'] }}')" aria-label="{{ __('checkout.more') }}" class="size-[26px] rounded-lg bg-[#F3F5F9] text-sm text-navy">+</button>
                                            <button type="button" wire:click="remove('{{ $line['key'] }}')" class="ml-2 p-0 text-xs font-semibold text-subtle hover:text-sale">{{ __('checkout.remove') }}</button>
                                        </div>
                                    </div>
                                    <span class="font-display text-sm font-extrabold whitespace-nowrap">{{ $line['lineLabel'] }}</span>
                                </div>
                            @empty
                                <div class="border-t border-line-soft py-5 text-center text-[13px] text-muted">
                                    {{ __('checkout.empty') }} · <a href="{{ route('home') }}" wire:navigate class="font-bold">{{ __('checkout.products') }}</a>
                                </div>
                            @endforelse
                            @error('cart')<span class="text-center text-xs font-semibold text-sale">{{ $message }}</span>@enderror
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <div class="flex gap-2">
                                <input
                                    wire:model="promoInput"
                                    wire:keydown.enter.prevent="applyPromo"
                                    aria-label="{{ __('checkout.promo_placeholder') }}"
                                    placeholder="{{ __('checkout.promo_placeholder') }}"
                                    @disabled($promoCode)
                                    class="h-[46px] min-w-0 flex-1 rounded-xl border-[1.5px] border-dashed bg-white px-3.5 font-display text-[13px] font-bold tracking-[0.5px] text-navy uppercase outline-none placeholder:font-sans placeholder:normal-case focus:border-brand {{ $promoCode ? 'border-[#1FB866]' : 'border-[#D5DEEC]' }}"
                                >
                                @if ($promoCode)
                                    <button type="button" wire:click="removePromo" class="h-[46px] rounded-xl bg-[#F3F5F9] px-4 text-[13px] font-bold text-navy hover:bg-navy hover:text-white">{{ __('checkout.promo_remove') }}</button>
                                @else
                                    <button type="button" wire:click="applyPromo" class="h-[46px] rounded-xl bg-[#F3F5F9] px-4 text-[13px] font-bold text-navy hover:bg-navy hover:text-white">{{ __('checkout.promo_apply') }}</button>
                                @endif
                            </div>
                            @if ($promoMessage)
                                <span class="text-xs font-semibold {{ $promoFailed ? 'text-sale' : 'text-[#14804A]' }}">{{ $promoMessage }}</span>
                            @endif
                        </div>

                        <div class="flex flex-col gap-2.5 text-sm">
                            <div class="flex justify-between text-muted"><span>{{ __('checkout.products') }}</span><span class="font-display font-bold text-navy">{{ Catalog::money($this->totals['subtotal']) }}</span></div>
                            @if ($this->totals['discount'] > 0)
                                <div class="flex justify-between text-[#14804A]"><span>{{ __('checkout.discount', ['code' => $this->totals['promo']]) }}</span><span class="font-display font-bold">−{{ Catalog::money($this->totals['discount']) }}</span></div>
                            @endif
                            @if ($this->totals['shipping'] > 0)
                                <div class="flex justify-between text-muted"><span>{{ __('checkout.shipping') }}</span><span class="font-display font-bold text-navy">{{ Catalog::money($this->totals['shipping']) }}</span></div>
                            @endif
                            <div class="flex items-baseline justify-between border-t border-line pt-3.5">
                                <span class="font-extrabold">{{ __('checkout.total') }}</span>
                                <span class="font-display text-[28px] font-extrabold tracking-[-0.8px]">{{ Catalog::money($this->totals['total']) }}</span>
                            </div>
                        </div>

                        <label class="flex cursor-pointer items-start gap-2.5 text-xs leading-normal text-muted">
                            <input type="checkbox" wire:model="agree" class="m-0 size-[18px] shrink-0 accent-brand">
                            <span>{!! __('checkout.agree', ['terms' => '<a href="#" class="font-bold">'.e(__('checkout.terms')).'</a>']) !!}</span>
                        </label>
                        @error('agree')<span class="-mt-2 text-xs font-semibold text-sale">{{ $message }}</span>@enderror

                        <button type="submit" wire:loading.attr="disabled" wire:target="placeOrder" @disabled(! $this->lines) class="flex h-[58px] items-center justify-center gap-2.5 rounded-2xl bg-brand text-[15px] font-extrabold text-white shadow-[0_18px_36px_-16px_rgba(11,99,246,.8)] hover:bg-brand-dark disabled:cursor-not-allowed disabled:opacity-60">
                            @if ($chosenMethod?->isPayableOnline() && str_contains((string) $chosenMethod->driver, 'installment'))
                                {{ __('checkout.apply_installment') }}
                            @elseif ($chosenMethod?->isPayableOnline())
                                {{ __('checkout.pay', ['amount' => Catalog::money($this->totals['total'])]) }}
                            @else
                                {{ __('checkout.confirm') }}
                            @endif
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </button>
                        @if ($errors->isNotEmpty())
                            <span class="-mt-1.5 text-center text-xs font-semibold text-sale">{{ __('checkout.fix_errors') }}</span>
                        @endif
                    </div>
                    <div class="flex flex-wrap justify-between gap-2.5 px-1.5 text-xs text-muted">
                        @foreach (__('checkout.perks') as $perk)
                            <span>{{ $perk }}</span><span>·</span>
                        @endforeach
                        <a href="{{ Site::phoneHref() }}" class="text-muted hover:text-brand">{{ Site::phone() }}</a>
                    </div>
                </aside>
            </form>
        </main>
    @endif
</div>
