<?php

use App\Models\User;
use App\Support\Catalog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    public const TABS = ['overview', 'orders', 'wishlist', 'addresses', 'profile'];

    #[Url(except: 'overview')]
    public string $tab = 'overview';

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $current_password = '';

    public string $password = '';

    public string $addressTitle = '';

    public string $addressLine = '';

    public bool $profileSaved = false;

    public bool $passwordUpdated = false;

    public function mount(): void
    {
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'overview';
        }

        $this->fillProfile();
    }

    #[Computed]
    public function user(): User
    {
        return Auth::user();
    }

    #[Computed]
    public function orders()
    {
        return $this->user->orders()->with('items')->limit(50)->get();
    }

    #[Computed]
    public function addresses()
    {
        return $this->user->addresses()->get();
    }

    public function show(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'overview';
        $this->resetErrorBag();
        $this->profileSaved = false;
        $this->passwordUpdated = false;
    }

    public function saveProfile(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user->id)],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\-\s()]{6,32}$/'],
        ]);

        $user = $this->user;
        $user->fill([...$validated, 'phone' => $validated['phone'] ?: null]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->profileSaved = true;
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', Password::defaults()],
        ]);

        $this->user->update(['password' => $this->password]);

        $this->reset('current_password', 'password');
        $this->passwordUpdated = true;
    }

    public function toggleNotification(string $channel): void
    {
        if (! in_array($channel, User::NOTIFICATION_CHANNELS, true)) {
            return;
        }

        $user = $this->user;
        $preferences = collect(User::NOTIFICATION_CHANNELS)
            ->mapWithKeys(fn (string $key) => [$key => $user->wantsNotification($key)])
            ->all();

        $preferences[$channel] = ! $preferences[$channel];

        $user->update(['notification_preferences' => $preferences]);
    }

    public function addAddress(): void
    {
        $validated = $this->validate([
            'addressTitle' => ['required', 'string', 'max:80'],
            'addressLine' => ['required', 'string', 'max:255'],
        ]);

        $this->user->addresses()->create([
            'title' => $validated['addressTitle'],
            'line' => $validated['addressLine'],
            'is_default' => $this->addresses->isEmpty(),
        ]);

        $this->reset('addressTitle', 'addressLine');
        unset($this->addresses);

        $this->dispatch('toast', message: __('account.addresses.added'));
    }

    public function makeDefaultAddress(int $id): void
    {
        $address = $this->user->addresses()->findOrFail($id);

        $this->user->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        unset($this->addresses);
    }

    public function deleteAddress(int $id): void
    {
        $address = $this->user->addresses()->findOrFail($id);
        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $this->user->addresses()->first()?->update(['is_default' => true]);
        }

        unset($this->addresses);

        $this->dispatch('toast', message: __('account.addresses.deleted'));
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();

        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(route('home'));
    }

    public function render()
    {
        return $this->view([
            'wishProducts' => collect($this->user->favoriteIds())
                ->map(fn (int $id) => Catalog::find($id))
                ->filter()
                ->map(fn (array $product) => collect($product)->only(['id', 'url', 'image', 'name', 'brand', 'priceLabel', 'monthlyLabel']))
                ->values(),
        ])->title(__('account.title'));
    }

    protected function fillProfile(): void
    {
        $this->name = $this->user->name;
        $this->email = $this->user->email;
        $this->phone = (string) $this->user->phone;
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => __('account.profile.full_name'),
            'email' => __('account.profile.email'),
            'phone' => __('account.profile.phone'),
            'current_password' => __('account.profile.current_password'),
            'password' => __('account.profile.new_password'),
            'addressTitle' => __('account.addresses.title_label'),
            'addressLine' => __('account.addresses.line_label'),
        ];
    }
};
?>

@php
    $icons = [
        'overview' => 'M3 11 12 4l9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
        'orders' => 'M6 7h12l-1 13H7zM9 7a3 3 0 0 1 6 0',
        'wishlist' => 'M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8z',
        'addresses' => 'M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21zM12 12a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z',
        'profile' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21c1.5-4 4.5-6 8-6s6.5 2 8 6',
    ];
    $inputClass = 'h-[50px] rounded-[14px] border-[1.5px] bg-white px-4 text-[15px] font-medium text-navy outline-0 focus:border-brand';
@endphp

<main class="mx-auto max-w-[1480px] px-4 pt-6 pb-16 sm:px-6 lg:px-8">
    <nav aria-label="breadcrumb" class="mb-[18px] flex items-center gap-2 text-[13px] text-muted">
        <a href="{{ route('home') }}" wire:navigate class="text-muted hover:text-brand">{{ __('account.home') }}</a>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
        <span>{{ __('account.title') }}</span>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
        <span class="font-semibold text-navy">{{ __("account.tabs.$tab") }}</span>
    </nav>

    <div class="grid items-start gap-5 lg:grid-cols-[300px_minmax(0,1fr)] lg:gap-7">
        <aside class="flex min-w-0 flex-col gap-3 lg:sticky lg:top-[150px]">
            <div class="relative flex flex-col gap-[18px] overflow-hidden rounded-3xl bg-navy px-4 py-4 text-white sm:px-[22px] sm:py-6">
                <div class="pointer-events-none absolute -top-[90px] -right-[70px] size-[230px] rounded-full bg-[radial-gradient(circle,rgba(20,180,255,.3),transparent_65%)]"></div>
                <div class="relative flex items-center gap-3.5">
                    <span class="grid size-12 shrink-0 place-items-center rounded-full bg-brand font-display text-[17px] font-extrabold shadow-[0_0_0_4px_rgba(255,255,255,.08)] sm:size-14 sm:text-[19px]">{{ $this->user->initials() }}</span>
                    <span class="flex min-w-0 flex-col gap-[3px]">
                        <span class="text-[17px] font-extrabold">{{ $this->user->name }}</span>
                        <span class="truncate text-xs text-faint">{{ $this->user->email }}</span>
                    </span>
                </div>
            </div>

            <nav class="grid grid-cols-3 gap-1.5 font-caps lg:flex lg:flex-col lg:gap-0.5 lg:rounded-3xl lg:border lg:border-line-mid lg:bg-white lg:p-2">
                @foreach (array_keys($icons) as $key)
                    <button
                        type="button"
                        wire:click="show('{{ $key }}')"
                        @if ($tab === $key) aria-current="page" @endif
                        @class([
                            'relative flex min-w-0 flex-col items-center justify-center gap-1.5 rounded-2xl px-1.5 py-2.5 text-center text-[11px] leading-tight font-bold lg:h-12 lg:flex-row lg:justify-start lg:gap-3 lg:rounded-[14px] lg:px-3.5 lg:py-0 lg:text-left lg:text-sm lg:whitespace-nowrap',
                            'bg-navy text-white' => $tab === $key,
                            'bg-soft text-navy hover:bg-tint lg:bg-transparent lg:hover:bg-soft' => $tab !== $key,
                        ])
                    >
                        <span @class(['grid size-5 shrink-0 place-items-center lg:size-8 lg:rounded-[10px]', 'text-sky lg:bg-brand lg:text-white' => $tab === $key, 'text-muted lg:bg-soft lg:text-navy' => $tab !== $key])>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons[$key] }}"/></svg>
                        </span>
                        <span class="lg:flex-1">{{ __("account.tabs.$key") }}</span>
                        @if ($key === 'wishlist')
                            <span x-show="$store.favorites.count" x-cloak x-text="$store.favorites.count" class="absolute top-1.5 right-1.5 h-[18px] min-w-[18px] rounded-[9px] bg-tint px-[5px] text-center font-display text-[10px] leading-[18px] font-extrabold text-brand lg:static lg:h-[22px] lg:min-w-[22px] lg:rounded-[11px] lg:px-[7px] lg:text-[11px] lg:leading-[22px]"></span>
                        @elseif ($key === 'addresses' && $this->addresses->count())
                            <span class="absolute top-1.5 right-1.5 h-[18px] min-w-[18px] rounded-[9px] bg-tint px-[5px] text-center font-display text-[10px] leading-[18px] font-extrabold text-brand lg:static lg:h-[22px] lg:min-w-[22px] lg:rounded-[11px] lg:px-[7px] lg:text-[11px] lg:leading-[22px]">{{ $this->addresses->count() }}</span>
                        @endif
                    </button>
                @endforeach
                <div class="mx-2.5 my-1.5 hidden h-px bg-[#F0F3F8] lg:block"></div>
                <button type="button" wire:click="logout" class="flex min-w-0 flex-col items-center justify-center gap-1.5 rounded-2xl bg-[#FFF1F1] px-1.5 py-2.5 text-center text-[11px] leading-tight font-bold text-sale lg:h-12 lg:flex-row lg:justify-start lg:gap-3 lg:rounded-[14px] lg:bg-transparent lg:px-3.5 lg:py-0 lg:text-left lg:text-sm lg:whitespace-nowrap lg:hover:bg-[#FFF1F1]">
                    <span class="grid size-5 place-items-center lg:size-8 lg:rounded-[10px] lg:bg-[#FFF1F1]">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 16l-4-4 4-4M6 12h10"/></svg>
                    </span>
                    {{ __('account.logout') }}
                </button>
            </nav>
        </aside>

        <section class="flex min-w-0 animate-drop flex-col gap-4" wire:key="tab-{{ $tab }}">
            @if ($tab === 'overview')
                <h1 class="m-0 text-[26px] font-extrabold tracking-[0.3px] sm:text-[30px]">{{ __('account.hello', ['name' => $this->user->firstName()]) }}</h1>
                <div class="grid gap-4 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
                    <div class="flex flex-col rounded-3xl border border-line-mid bg-white px-[26px] py-6">
                        <div class="mb-2.5 flex items-center justify-between">
                            <span class="text-base font-extrabold">{{ __('account.overview.recent_orders') }}</span>
                            <button type="button" wire:click="show('orders')" class="text-[13px] font-bold text-brand">{{ __('account.overview.all') }}</button>
                        </div>
                        @forelse ($this->orders->take(3) as $order)
                            <button type="button" wire:key="recent-order-{{ $order->id }}" wire:click="show('orders')" class="flex items-center justify-between gap-3 border-t border-[#F0F3F8] py-3 text-left text-navy">
                                <span class="flex min-w-0 flex-col gap-0.5">
                                    <span class="font-display text-sm font-extrabold">#{{ $order->number }}</span>
                                    <span class="truncate text-xs text-muted">{{ $order->created_at->format('d.m.Y') }} · {{ trans_choice('account.orders.items', $order->items->sum('quantity'), ['count' => $order->items->sum('quantity')]) }}</span>
                                </span>
                                <span class="flex shrink-0 flex-col items-end gap-1">
                                    <span class="font-display text-sm font-extrabold">{{ Catalog::money($order->total) }}</span>
                                    <span class="rounded-full bg-tint px-2 py-0.5 text-[11px] font-bold text-brand">{{ __("account.orders.statuses.{$order->status}") }}</span>
                                </span>
                            </button>
                        @empty
                        <div class="flex flex-1 flex-col items-center justify-center gap-2 border-t border-[#F0F3F8] py-8 text-center">
                            <span class="grid size-12 place-items-center rounded-xl bg-[#F3F6FB] text-brand">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons['orders'] }}"/></svg>
                            </span>
                            <span class="text-sm font-bold">{{ __('account.orders.empty') }}</span>
                            <a href="{{ route('home') }}" wire:navigate class="text-[13px] font-bold">{{ __('account.orders.start') }} →</a>
                        </div>
                        @endforelse
                    </div>
                    <div class="flex flex-col gap-2.5 rounded-3xl border border-line-mid bg-white px-[26px] py-[22px]">
                        <div class="flex items-center justify-between">
                            <span class="text-base font-extrabold">{{ __('account.overview.delivery_address') }}</span>
                            <button type="button" wire:click="show('addresses')" class="text-[13px] font-bold text-brand">{{ $this->addresses->isEmpty() ? __('account.overview.add_address') : __('account.overview.change') }}</button>
                        </div>
                        @if ($default = $this->addresses->firstWhere('is_default', true) ?? $this->addresses->first())
                            <span class="text-sm font-bold">{{ $default->title }}</span>
                            <span class="text-[13px] leading-normal text-muted">{{ $default->line }}</span>
                        @else
                            <span class="text-[13px] text-muted">{{ __('account.overview.no_address') }}</span>
                        @endif
                    </div>
                </div>
            @endif

            @if ($tab === 'orders')
                <h1 class="m-0 text-[26px] font-extrabold tracking-[0.3px] sm:text-[30px]">{{ __('account.orders.title') }}</h1>
                @foreach ($this->orders as $order)
                    <article wire:key="order-{{ $order->id }}" class="flex flex-col gap-3 rounded-3xl border border-line-mid bg-white px-5 py-5 sm:px-[26px]">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-col gap-0.5">
                                <a href="{{ $order->pageUrl() }}" class="font-display text-base font-extrabold text-navy hover:text-brand">#{{ $order->number }}</a>
                                <span class="text-xs text-muted">{{ $order->created_at->format('d.m.Y H:i') }} · {{ $order->paymentLabel() }} · {{ __("payments.statuses.{$order->payment_status}") }}</span>
                                <a href="{{ $order->invoiceUrl(app()->getLocale()) }}" target="_blank" rel="noopener" class="mt-1 inline-flex w-fit items-center gap-1.5 text-xs font-bold text-brand hover:text-brand-dark">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/></svg>
                                    {{ __('invoice.download') }}
                                </a>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="rounded-full bg-tint px-2.5 py-1 text-xs font-bold text-brand">{{ __("account.orders.statuses.{$order->status}") }}</span>
                                <span class="font-display text-lg font-extrabold">{{ Catalog::money($order->total) }}</span>
                            </div>
                        </div>
                        <div class="flex flex-col border-t border-[#F0F3F8]">
                            @foreach ($order->items as $item)
                                @php($orderedProduct = $item->product_id ? Catalog::find($item->product_id) : null)
                                <div class="flex items-center gap-3.5 border-b border-[#F0F3F8] py-3 text-sm last:border-b-0">
                                    <span class="grid size-16 shrink-0 place-items-center overflow-hidden rounded-2xl bg-[radial-gradient(120%_90%_at_50%_35%,#FFFFFF_0%,#F1F5FB_60%,#E6EDF8_100%)]">
                                        @if ($orderedProduct && $orderedProduct['images'])
                                            <img src="{{ $orderedProduct['images'][0] }}" alt="{{ $item->name }}" loading="lazy" class="size-full object-contain p-1.5">
                                        @else
                                            <span class="font-display text-xl font-extrabold text-brand/30">{{ mb_strtoupper(mb_substr($item->name, 0, 1)) }}</span>
                                        @endif
                                    </span>
                                    <span class="flex min-w-0 flex-1 flex-col gap-1">
                                        @if ($orderedProduct)
                                            <a href="{{ $orderedProduct['url'] }}" wire:navigate class="line-clamp-2 font-semibold text-navy hover:text-brand">{{ $item->name }}</a>
                                        @else
                                            <span class="line-clamp-2 font-semibold">{{ $item->name }}</span>
                                        @endif
                                        @if ($item->options)
                                            <span class="text-xs text-subtle">{{ implode(' · ', $item->options) }}</span>
                                        @endif
                                        <span class="text-xs text-muted sm:hidden">{{ $item->quantity }} × {{ Catalog::money($item->unit_price) }}</span>
                                    </span>
                                    <span class="hidden shrink-0 text-right sm:flex sm:flex-col sm:gap-0.5">
                                        <span class="font-bold text-navy">{{ Catalog::money($item->total) }}</span>
                                        <span class="text-xs text-muted">{{ $item->quantity }} × {{ Catalog::money($item->unit_price) }}</span>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </article>
                @endforeach
                <div @class(['flex flex-col items-center gap-3 rounded-3xl border border-line-mid bg-white px-6 py-14 text-center', 'hidden' => $this->orders->isNotEmpty()])>
                    <span class="grid size-16 place-items-center rounded-full bg-tint text-brand">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons['orders'] }}"/></svg>
                    </span>
                    <span class="text-lg font-extrabold">{{ __('account.orders.empty') }}</span>
                    <span class="max-w-[380px] text-sm text-muted">{{ __('account.orders.empty_text') }}</span>
                    <a href="{{ route('home') }}" wire:navigate class="mt-2 flex h-11 items-center rounded-full bg-brand px-6 text-sm font-bold text-white hover:bg-brand-dark hover:text-white">{{ __('account.orders.start') }}</a>
                </div>
            @endif

            @if ($tab === 'wishlist')
                <h1 class="m-0 text-[26px] font-extrabold tracking-[0.3px] sm:text-[30px]">{{ __('account.wishlist.title') }}</h1>
                <div x-data="{ products: @js($wishProducts), get items() { return this.products.filter((product) => $store.favorites.has(product.id)) } }">
                    <div x-show="items.length" class="grid grid-cols-2 gap-3.5 sm:grid-cols-[repeat(auto-fill,minmax(230px,1fr))]">
                        <template x-for="product in items" :key="product.id">
                            <article class="relative flex flex-col rounded-[18px] border border-line-mid bg-white px-2 pt-2 pb-4">
                                <div class="relative grid aspect-square place-items-center rounded-[14px] bg-[#F3F6FB]">
                                    <span class="font-display text-[64px] leading-none font-extrabold text-brand/15" x-text="product.brand[0]"></span>
                                    <button type="button" x-on:click="$store.favorites.toggle(product.id, @js(__('shop.favorites_added')), @js(__('shop.favorites_removed')))" aria-label="{{ __('account.wishlist.remove') }}" class="absolute top-2.5 right-2.5 grid size-9 place-items-center rounded-full bg-white text-sale shadow-[0_4px_12px_-4px_rgba(10,22,51,.2)]">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="{{ $icons['wishlist'] }}"/></svg>
                                    </button>
                                </div>
                                <div class="flex flex-1 flex-col gap-2 px-2 pt-3">
                                    <span class="text-sm leading-[1.4] font-semibold" x-text="product.name"></span>
                                    <span class="text-xs text-muted" x-text="@js(__('account.wishlist.monthly', ['amount' => '__A__'])).replace('__A__', product.monthlyLabel)"></span>
                                    <div class="mt-auto flex items-center justify-between gap-2">
                                        <span class="font-display text-lg font-extrabold" x-text="product.priceLabel"></span>
                                        <button type="button" x-on:click="Livewire.dispatch('cart-add', { id: product.id })" class="h-10 rounded-xl px-3.5 text-[13px] font-bold text-white" :class="$store.cart.qty(product.id) ? 'bg-[#1FB866]' : 'bg-brand hover:bg-brand-dark'" x-text="$store.cart.qty(product.id) ? '✓ ' + $store.cart.qty(product.id) : @js(__('account.wishlist.add'))"></button>
                                    </div>
                                </div>
                            </article>
                        </template>
                    </div>
                    <div x-show="! items.length" class="rounded-3xl border border-line-mid bg-white p-12 text-center text-sm text-muted">
                        {{ __('account.wishlist.empty') }} · <a href="{{ route('home') }}" wire:navigate class="font-bold">{{ __('account.wishlist.browse') }}</a>
                    </div>
                </div>
            @endif

            @if ($tab === 'addresses')
                <h1 class="m-0 text-[26px] font-extrabold tracking-[0.3px] sm:text-[30px]">{{ __('account.addresses.title') }}</h1>
                <div class="grid gap-3.5 sm:grid-cols-[repeat(auto-fill,minmax(300px,1fr))]">
                    @foreach ($this->addresses as $address)
                        <div wire:key="address-{{ $address->id }}" @class(['flex flex-col gap-2.5 rounded-[22px] border-[1.5px] bg-white px-6 py-[22px]', 'border-brand' => $address->is_default, 'border-line-mid' => ! $address->is_default])>
                            <div class="flex items-center justify-between gap-2.5">
                                <span class="text-[15px] font-extrabold">{{ $address->title }}</span>
                                @if ($address->is_default)
                                    <span class="rounded-full bg-tint px-2.5 py-[3px] text-xs font-bold text-brand">{{ __('account.addresses.default') }}</span>
                                @endif
                            </div>
                            <span class="text-[13px] leading-[1.55] text-muted">{{ $address->line }}</span>
                            <div class="mt-1 flex gap-3.5">
                                @unless ($address->is_default)
                                    <button type="button" wire:click="makeDefaultAddress({{ $address->id }})" class="text-[13px] font-bold text-brand">{{ __('account.addresses.make_default') }}</button>
                                @endunless
                                <button type="button" wire:click="deleteAddress({{ $address->id }})" wire:confirm="{{ __('account.addresses.delete') }}?" class="text-[13px] font-semibold text-subtle hover:text-sale">{{ __('account.addresses.delete') }}</button>
                            </div>
                        </div>
                    @endforeach

                    <form wire:submit="addAddress" class="flex flex-col gap-2.5 rounded-[22px] border-[1.5px] border-dashed border-[#C9D3E3] bg-[#FAFBFD] px-6 py-[22px]">
                        <span class="text-[15px] font-extrabold">{{ __('account.addresses.new') }}</span>
                        <input wire:model="addressTitle" placeholder="{{ __('account.addresses.title_placeholder') }}" aria-label="{{ __('account.addresses.title_label') }}" @class(['h-[46px] rounded-xl border-[1.5px] bg-white px-3.5 text-sm text-navy outline-0 focus:border-brand', 'border-sale' => $errors->has('addressTitle'), 'border-line' => ! $errors->has('addressTitle')])>
                        @error('addressTitle') <span class="text-xs text-sale">{{ $message }}</span> @enderror
                        <input wire:model="addressLine" placeholder="{{ __('account.addresses.line_placeholder') }}" aria-label="{{ __('account.addresses.line_label') }}" @class(['h-[46px] rounded-xl border-[1.5px] bg-white px-3.5 text-sm text-navy outline-0 focus:border-brand', 'border-sale' => $errors->has('addressLine'), 'border-line' => ! $errors->has('addressLine')])>
                        @error('addressLine') <span class="text-xs text-sale">{{ $message }}</span> @enderror
                        <button type="submit" wire:loading.attr="disabled" class="h-11 rounded-xl bg-brand text-sm font-bold text-white hover:bg-brand-dark disabled:opacity-60">{{ __('account.addresses.add') }}</button>
                    </form>
                </div>
            @endif

            @if ($tab === 'profile')
                <h1 class="m-0 text-[26px] font-extrabold tracking-[0.3px] sm:text-[30px]">{{ __('account.profile.title') }}</h1>
                <form wire:submit="saveProfile" class="flex flex-col gap-[18px] rounded-3xl border border-line-mid bg-white px-5 py-6 sm:px-8 sm:py-7">
                    <div class="grid gap-3 sm:grid-cols-[repeat(auto-fit,minmax(240px,1fr))]">
                        @foreach ([['name', 'text', 'name'], ['phone', 'tel', 'tel'], ['email', 'email', 'email']] as [$field, $type, $autocomplete])
                            <label class="flex flex-col gap-1.5 text-xs font-bold text-[#3A4A68]">
                                {{ __('account.profile.'.($field === 'name' ? 'full_name' : $field)) }}
                                <input wire:model="{{ $field }}" type="{{ $type }}" autocomplete="{{ $autocomplete }}" class="{{ $inputClass }} {{ $errors->has($field) ? 'border-sale' : 'border-line' }}">
                                @error($field) <span class="text-xs font-medium text-sale">{{ $message }}</span> @enderror
                            </label>
                        @endforeach
                    </div>
                    <div class="flex items-center gap-3.5">
                        <button type="submit" wire:loading.attr="disabled" class="h-[50px] rounded-[14px] bg-brand px-[26px] text-sm font-extrabold text-white hover:bg-brand-dark disabled:opacity-60">{{ __('account.profile.save') }}</button>
                        @if ($profileSaved)
                            <span class="flex items-center gap-1.5 text-[13px] font-bold text-ok">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg>
                                {{ __('account.profile.saved') }}
                            </span>
                        @endif
                    </div>
                </form>

                <div class="grid gap-3.5 md:grid-cols-2">
                    <form wire:submit="updatePassword" class="flex flex-col gap-3 rounded-3xl border border-line-mid bg-white px-7 py-6">
                        <span class="text-base font-extrabold">{{ __('account.profile.password_title') }}</span>
                        <input wire:model="current_password" type="password" autocomplete="current-password" placeholder="{{ __('account.profile.current_password') }}" aria-label="{{ __('account.profile.current_password') }}" class="h-12 rounded-[14px] border-[1.5px] px-4 text-sm text-navy outline-0 focus:border-brand {{ $errors->has('current_password') ? 'border-sale' : 'border-line' }}">
                        @error('current_password') <span class="text-xs text-sale">{{ $message }}</span> @enderror
                        <input wire:model="password" type="password" autocomplete="new-password" placeholder="{{ __('account.profile.new_password') }}" aria-label="{{ __('account.profile.new_password') }}" class="h-12 rounded-[14px] border-[1.5px] px-4 text-sm text-navy outline-0 focus:border-brand {{ $errors->has('password') ? 'border-sale' : 'border-line' }}">
                        @error('password') <span class="text-xs text-sale">{{ $message }}</span> @enderror
                        <div class="flex items-center gap-3">
                            <button type="submit" wire:loading.attr="disabled" class="h-11 self-start rounded-xl bg-navy px-5 text-[13px] font-bold text-white hover:bg-brand disabled:opacity-60">{{ __('account.profile.update_password') }}</button>
                            @if ($passwordUpdated)
                                <span class="text-[13px] font-bold text-ok">✓ {{ __('account.profile.password_updated') }}</span>
                            @endif
                        </div>
                    </form>

                    <div class="flex flex-col gap-1 rounded-3xl border border-line-mid bg-white px-7 py-6">
                        <span class="mb-2 text-base font-extrabold">{{ __('account.profile.notifications') }}</span>
                        @foreach (App\Models\User::NOTIFICATION_CHANNELS as $channel)
                            @php($enabled = $this->user->wantsNotification($channel))
                            <button type="button" wire:click="toggleNotification('{{ $channel }}')" role="switch" aria-checked="{{ $enabled ? 'true' : 'false' }}" class="flex items-center justify-between gap-3.5 border-t border-[#F0F3F8] py-3 text-left text-navy">
                                <span class="flex flex-col gap-0.5">
                                    <span class="text-sm font-bold">{{ __("account.profile.channels.$channel.label") }}</span>
                                    <span class="text-xs text-subtle">{{ __("account.profile.channels.$channel.text") }}</span>
                                </span>
                                <span @class(['relative h-[26px] w-11 shrink-0 rounded-[13px] transition-colors', 'bg-brand' => $enabled, 'bg-[#D5DEEC]' => ! $enabled])>
                                    <span @class(['absolute top-[3px] size-5 rounded-full bg-white shadow-[0_1px_3px_rgba(10,22,51,.25)] transition-all', 'left-[21px]' => $enabled, 'left-[3px]' => ! $enabled])></span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
    </div>
</main>
