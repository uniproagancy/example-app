@php
    use App\Filament\Resources\Orders\OrderResource;
    use App\Models\Order;
    use App\Support\Catalog;

    $order = $this->order();
    $editable = $order->isEditable();
    $totals = $this->totals;
    $statuses = OrderResource::statusOptions();
    $label = 'text-sm font-medium leading-6 text-gray-950 dark:text-white';
    $error = 'mt-1 text-sm text-danger-600 dark:text-danger-400';
    $initials = collect(preg_split('/\s+/', trim($order->first_name.' '.$order->last_name)))
        ->filter()
        ->take(2)
        ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('') ?: '?';
@endphp

<x-filament-panels::page>
    <div class="flex flex-wrap items-center gap-2">
        <x-filament::badge :color="OrderResource::statusColor($order->status)" size="lg">
            {{ $statuses[$order->status] ?? $order->status }}
        </x-filament::badge>
        <x-filament::badge :color="OrderResource::paymentStatusColor($order->payment_status)" icon="heroicon-o-banknotes" size="lg">
            {{ OrderResource::paymentStatusOptions()[$order->payment_status] ?? $order->payment_status }} · {{ OrderResource::paymentLabel($order->payment_method) }}
        </x-filament::badge>
        @if ($order->isQuick())
            <x-filament::badge color="info" icon="heroicon-o-bolt" size="lg">
                {{ OrderResource::sourceOptions()['quick'] }}
            </x-filament::badge>
        @endif
        @if ($order->promo_code)
            <x-filament::badge color="success" icon="heroicon-o-ticket" size="lg">{{ $order->promo_code }}</x-filament::badge>
        @endif
    </div>

    @unless ($editable)
        <x-filament::callout icon="heroicon-o-lock-closed" color="gray" :heading="__('admin.orders.closed')" :description="__('admin.orders.closed_help')" />
    @endunless

    @if ($order->isQuick() && $editable)
        <x-filament::callout icon="heroicon-o-phone" color="info" :description="__('admin.orders.quick_note')" />
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="flex flex-col gap-6 lg:col-span-2">
            <x-filament::section icon="heroicon-o-shopping-bag" :heading="__('admin.orders.items')">
                <x-slot name="afterHeader">
                    <x-filament::badge color="gray">{{ collect($lines)->sum('quantity') }}</x-filament::badge>
                </x-slot>

                @if ($editable)
                    <div class="relative mb-4">
                        <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass">
                            <x-filament::input
                                type="search"
                                wire:model.live.debounce.350ms="productSearch"
                                :placeholder="__('admin.orders.find_product')"
                                autocomplete="off"
                            />
                        </x-filament::input.wrapper>

                        @if ($this->foundProducts->isNotEmpty())
                            <div class="absolute inset-x-0 top-full z-20 mt-1 overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10">
                                @foreach ($this->foundProducts as $product)
                                    <button
                                        type="button"
                                        wire:key="found-{{ $product->id }}"
                                        wire:click="add({{ $product->id }})"
                                        class="flex w-full items-center gap-3 px-3 py-2 text-start hover:bg-gray-50 dark:hover:bg-white/5"
                                    >
                                        @if ($image = $this->imageOf($product))
                                            <img src="{{ $image }}" alt="" class="size-9 shrink-0 rounded-lg object-contain bg-white" loading="lazy">
                                        @else
                                            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-gray-100 text-gray-400 dark:bg-white/5">
                                                <x-filament::icon icon="heroicon-o-cube" class="size-5" />
                                            </span>
                                        @endif
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $product->kaTranslation?->name ?? $product->sku }}</span>
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $product->sku }}@unless ($product->is_active) · {{ __('admin.orders.product_hidden') }}@endunless</span>
                                        </span>
                                        <span class="shrink-0 text-sm font-semibold text-gray-950 dark:text-white">{{ Catalog::money($this->priceOf($product)) }}</span>
                                        <x-filament::badge :color="$product->stock > 0 ? 'success' : 'danger'" size="sm">
                                            {{ $product->stock > 0 ? __('admin.orders.in_stock', ['count' => $product->stock]) : __('admin.orders.no_stock') }}
                                        </x-filament::badge>
                                    </button>
                                @endforeach
                            </div>
                        @elseif (mb_strlen(trim($productSearch)) >= 2)
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('admin.orders.nothing_found') }}</p>
                        @endif
                    </div>
                @endif

                <div class="-mx-6 divide-y divide-gray-100 border-y border-gray-100 dark:divide-white/5 dark:border-white/5">
                    @forelse ($lines as $key => $line)
                        @php($product = $this->lineProducts->get($line['product_id']))
                        <div wire:key="line-{{ $key }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-6 py-3">
                            @if ($image = $this->imageOf($product))
                                <img src="{{ $image }}" alt="" class="size-12 shrink-0 rounded-lg bg-white object-contain ring-1 ring-gray-950/5" loading="lazy">
                            @else
                                <span class="grid size-12 shrink-0 place-items-center rounded-lg bg-gray-100 text-gray-400 dark:bg-white/5">
                                    <x-filament::icon icon="heroicon-o-cube" class="size-6" />
                                </span>
                            @endif

                            <div class="min-w-0 flex-1">
                                @if ($url = $this->siteUrlOf($product))
                                    <a href="{{ $url }}" target="_blank" rel="noopener" class="block truncate text-sm font-semibold text-gray-950 hover:text-primary-600 dark:text-white">{{ $line['name'] }}</a>
                                @else
                                    <span class="block truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $line['name'] }}</span>
                                @endif
                                <span class="block text-xs text-gray-500 dark:text-gray-400">
                                    {{ $product?->sku ?? __('admin.orders.product_deleted') }}
                                    @if ($line['options'])
                                        · {{ implode(' · ', (array) $line['options']) }}
                                    @endif
                                </span>
                            </div>

                            <span class="w-24 text-end text-sm text-gray-600 dark:text-gray-300">{{ Catalog::money($line['price']) }}</span>

                            <div class="flex items-center gap-1">
                                @if ($editable)
                                    <x-filament::icon-button icon="heroicon-m-minus" color="gray" size="sm" :label="__('admin.orders.less')" wire:click="decrement('{{ $key }}')" :disabled="$line['quantity'] <= 1" />
                                @endif
                                <span class="min-w-8 text-center text-sm font-semibold text-gray-950 dark:text-white">{{ $line['quantity'] }}</span>
                                @if ($editable)
                                    <x-filament::icon-button icon="heroicon-m-plus" color="gray" size="sm" :label="__('admin.orders.more')" wire:click="increment('{{ $key }}')" />
                                @endif
                            </div>

                            <span class="w-24 text-end text-sm font-bold text-gray-950 dark:text-white">{{ Catalog::money($line['price'] * $line['quantity']) }}</span>

                            @if ($editable)
                                <x-filament::icon-button icon="heroicon-o-trash" color="danger" size="sm" :label="__('admin.orders.remove')" wire:click="remove('{{ $key }}')" />
                            @endif
                        </div>
                    @empty
                        <p class="px-6 py-6 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('admin.orders.needs_item') }}</p>
                    @endforelse
                </div>
                @error('lines') <p class="{{ $error }}">{{ $message }}</p> @enderror

                <dl class="ms-auto mt-4 flex max-w-xs flex-col gap-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('admin.orders.subtotal') }}</dt>
                        <dd class="text-gray-950 dark:text-white">{{ Catalog::money($totals['subtotal']) }}</dd>
                    </div>
                    @if ($totals['discount'] > 0)
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('admin.orders.discount') }}@if ($order->promo_code) ({{ $order->promo_code }})@endif</dt>
                            <dd class="text-success-600 dark:text-success-400">−{{ Catalog::money($totals['discount']) }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('admin.orders.shipping') }}</dt>
                        <dd class="text-gray-950 dark:text-white">{{ $totals['shipping'] > 0 ? Catalog::money($totals['shipping']) : __('admin.orders.free') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-t border-gray-100 pt-2 dark:border-white/5">
                        <dt class="font-bold text-gray-950 dark:text-white">{{ __('admin.orders.total') }}</dt>
                        <dd class="text-lg font-bold text-gray-950 dark:text-white">{{ Catalog::money($totals['total']) }}</dd>
                    </div>
                </dl>
            </x-filament::section>

            <x-filament::section icon="heroicon-o-clock" :heading="__('admin.orders.history')">
                <ol class="relative flex flex-col gap-5 border-s border-gray-200 ps-6 dark:border-white/10">
                    @foreach ($this->events as $event)
                        <li wire:key="event-{{ $event->id }}" class="relative">
                            <span @class([
                                'absolute -start-[1.95rem] top-1 size-3 rounded-full ring-4 ring-white dark:ring-gray-900',
                                'bg-primary-500' => $event->type === 'status',
                                'bg-warning-500' => $event->type === 'edit',
                                'bg-success-500' => $event->type === 'payment',
                                'bg-gray-400' => $event->type === 'note',
                                'bg-info-500' => $event->type === 'opened',
                                'bg-danger-500' => $event->type === 'deleted',
                                'bg-success-600' => $event->type === 'restored',
                            ])></span>
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <p class="text-sm font-semibold text-gray-950 dark:text-white">
                                    @if ($event->type === 'status')
                                        {{ $statuses[$event->from] ?? $event->from }} → {{ $statuses[$event->to] ?? $event->to }}
                                    @elseif ($event->type === 'payment' && $event->to)
                                        {{ __('admin.orders.event_payment') }}: {{ __("payments.statuses.{$event->from}") }} → {{ __("payments.statuses.{$event->to}") }}
                                    @elseif ($event->type === 'payment')
                                        {{ __('admin.orders.event_payment') }}
                                    @elseif ($event->type === 'edit')
                                        {{ __('admin.orders.event_edit') }}
                                    @elseif (in_array($event->type, ['opened', 'deleted', 'restored'], true))
                                        {{ __("admin.orders.event_{$event->type}") }}
                                    @else
                                        {{ __('admin.orders.event_note') }}
                                    @endif
                                </p>
                                <time class="text-xs text-gray-500 dark:text-gray-400">{{ $event->created_at->format('d.m.Y H:i') }}</time>
                            </div>
                            @if ($event->note)
                                <p class="mt-1 whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">{{ $event->note }}</p>
                            @endif
                            @if ($event->user)
                                <p class="mt-1 flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                    <x-filament::icon icon="heroicon-m-user" class="size-3.5" />{{ $event->user->name }}
                                </p>
                            @endif
                        </li>
                    @endforeach
                    <li class="relative">
                        <span class="absolute -start-[1.95rem] top-1 size-3 rounded-full bg-success-500 ring-4 ring-white dark:ring-gray-900"></span>
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.orders.event_created', ['source' => OrderResource::sourceOptions()[$order->source] ?? $order->source]) }}</p>
                            <time class="text-xs text-gray-500 dark:text-gray-400">{{ $order->created_at->format('d.m.Y H:i') }}</time>
                        </div>
                    </li>
                </ol>

                <form wire:submit="addNote" class="mt-6 flex items-start gap-3">
                    <div class="flex-1">
                        <x-filament::input.wrapper :valid="! $errors->has('comment')">
                            <textarea wire:model="comment" rows="2" placeholder="{{ __('admin.orders.history_note') }}" class="block w-full resize-y border-none bg-transparent px-3 py-1.5 text-sm text-gray-950 outline-none placeholder:text-gray-400 focus:ring-0 dark:text-white"></textarea>
                        </x-filament::input.wrapper>
                        @error('comment') <p class="{{ $error }}">{{ $message }}</p> @enderror
                    </div>
                    <x-filament::button type="submit" color="gray" icon="heroicon-o-chat-bubble-left">{{ __('admin.orders.add_note') }}</x-filament::button>
                </form>
            </x-filament::section>
        </div>

        <div class="flex flex-col gap-6">
            <x-filament::section icon="heroicon-o-flag" :heading="__('admin.common.status')">
                <div class="flex flex-col gap-3">
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="status">
                            @foreach ($statuses as $value => $name)
                                <option value="{{ $value }}">{{ $name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    <x-filament::button wire:click="changeStatus" :disabled="$status === $order->status" class="w-full">
                        {{ __('admin.orders.change_status') }}
                    </x-filament::button>
                </div>
            </x-filament::section>

            <x-filament::section icon="heroicon-o-credit-card" :heading="__('admin.orders.payment')">
                <x-slot name="afterHeader">
                    <x-filament::badge :color="OrderResource::paymentStatusColor($order->payment_status)">
                        {{ OrderResource::paymentStatusOptions()[$order->payment_status] ?? $order->payment_status }}
                    </x-filament::badge>
                </x-slot>

                <div class="flex flex-col gap-3">
                    @if ($order->paid_at && $order->isPaid())
                        <p class="text-sm text-success-600 dark:text-success-400">{{ __('admin.orders.paid_at', ['when' => $order->paid_at->format('d.m.Y H:i')]) }}@if ($order->installment_months) · {{ __('checkout.months', ['count' => $order->installment_months]) }}@endif</p>
                    @endif

                    <label class="flex flex-col gap-1">
                        <span class="{{ $label }}">{{ __('admin.orders.payment_status') }}</span>
                        <div class="flex gap-2">
                            <x-filament::input.wrapper class="flex-1">
                                <x-filament::input.select wire:model.live="paymentStatus">
                                    @foreach (OrderResource::paymentStatusOptions() as $value => $name)
                                        <option value="{{ $value }}">{{ $name }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                            <x-filament::button color="gray" wire:click="changePaymentStatus" :disabled="$paymentStatus === $order->payment_status">
                                {{ __('admin.orders.set') }}
                            </x-filament::button>
                        </div>
                    </label>

                    <label class="flex flex-col gap-1">
                        <span class="{{ $label }}">{{ __('admin.orders.payment_method') }}</span>
                        <x-filament::input.wrapper :disabled="! $editable" :valid="! $errors->has('payment.payment_method')">
                            <x-filament::input.select wire:model.live="payment.payment_method" :disabled="! $editable">
                                @foreach ($this->paymentMethods as $method)
                                    <option value="{{ $method->code }}">{{ $method->name ?? $method->code }}@unless ($method->is_active) ({{ __('admin.common.inactive') }})@endunless</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                        @error('payment.payment_method') <span class="{{ $error }}">{{ $message }}</span> @enderror
                    </label>

                    @if ($order->bank || $order->term_months)
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ collect([$order->bank ? __("checkout.banks.{$order->bank}") : null, $order->term_months ? __('checkout.months', ['count' => $order->term_months]) : null])->filter()->implode(' · ') }}
                        </p>
                    @endif

                    @if (! $order->isPaid() && $order->paymentMethod?->isPayableOnline() && $order->status !== 'cancelled')
                        <div class="flex flex-col gap-1" x-data="{ copied: false }">
                            <span class="{{ $label }}">{{ __('admin.orders.pay_link') }}</span>
                            <x-filament::input.wrapper>
                                <x-filament::input type="text" readonly :value="$this->payLink()" x-ref="link" x-on:focus="$el.select()" />
                                <x-slot name="suffix">
                                    <button type="button" class="text-sm font-medium text-primary-600 dark:text-primary-400" x-on:click="navigator.clipboard?.writeText($refs.link.value); copied = true; setTimeout(() => copied = false, 1500)">
                                        <span x-show="! copied">{{ __('admin.orders.copy') }}</span>
                                        <span x-show="copied" x-cloak>{{ __('admin.orders.copied') }}</span>
                                    </button>
                                </x-slot>
                            </x-filament::input.wrapper>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.orders.pay_link_help') }}</span>
                        </div>
                    @endif

                    @if ($this->transactions->isNotEmpty())
                        <div class="flex flex-col gap-2">
                            <span class="{{ $label }}">{{ __('admin.orders.transactions') }}</span>
                            @foreach ($this->transactions as $transaction)
                                <div wire:key="transaction-{{ $transaction->id }}" class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-gray-50 px-3 py-2 text-xs dark:bg-white/5">
                                    <span class="font-medium text-gray-950 dark:text-white">{{ __("payments.drivers.{$transaction->driver}") }}</span>
                                    <span class="text-gray-600 dark:text-gray-300">{{ Catalog::money($transaction->amount) }}@if ($transaction->months) · {{ __('checkout.months', ['count' => $transaction->months]) }}@endif</span>
                                    <x-filament::badge size="sm" :color="match ($transaction->status) { 'success' => 'success', 'pending' => 'warning', default => 'danger' }">
                                        {{ __("admin.orders.transaction_statuses.{$transaction->status}") }}
                                    </x-filament::badge>
                                    <span class="w-full text-gray-500 dark:text-gray-400">{{ $transaction->created_at->format('d.m.Y H:i') }}@if ($transaction->checked_at) · {{ __('admin.orders.checked', ['when' => $transaction->checked_at->diffForHumans()]) }}@endif</span>
                                </div>
                            @endforeach
                            @unless ($order->isPaid())
                                <x-filament::button color="gray" icon="heroicon-o-arrow-path" wire:click="askBank" wire:target="askBank" class="w-full">
                                    {{ __('admin.orders.ask_bank') }}
                                </x-filament::button>
                            @endunless
                        </div>
                    @endif
                </div>
            </x-filament::section>

            <x-filament::section icon="heroicon-o-user" :heading="__('admin.orders.customer')">
                <div class="mb-4 flex items-center gap-3">
                    <span class="grid size-11 shrink-0 place-items-center rounded-full bg-primary-50 text-sm font-bold text-primary-600 dark:bg-primary-500/15 dark:text-primary-400">{{ $initials }}</span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ trim($order->first_name.' '.$order->last_name) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $order->user ? __('admin.orders.registered', ['email' => $order->user->email]) : __('admin.orders.guest') }}</p>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    @foreach (['first_name' => 'text', 'last_name' => 'text', 'phone' => 'tel', 'email' => 'email'] as $field => $type)
                        <label class="flex flex-col gap-1">
                            <span class="{{ $label }}">{{ __("admin.orders.$field") }}</span>
                            <x-filament::input.wrapper :valid="! $errors->has('customer.'.$field)" :disabled="! $editable">
                                <x-filament::input :type="$type" wire:model="customer.{{ $field }}" :disabled="! $editable" />
                            </x-filament::input.wrapper>
                            @error('customer.'.$field) <span class="{{ $error }}">{{ $message }}</span> @enderror
                        </label>
                    @endforeach
                </div>
            </x-filament::section>

            <x-filament::section icon="heroicon-o-truck" :heading="__('admin.orders.delivery')">
                <div class="flex flex-col gap-3">
                    <label class="flex flex-col gap-1">
                        <span class="{{ $label }}">{{ __('admin.orders.shipping_method') }}</span>
                        <x-filament::input.wrapper :disabled="! $editable">
                            <x-filament::input.select wire:model.live="delivery.shipping_method" :disabled="! $editable">
                                <option value="">{{ __('admin.orders.not_set') }}</option>
                                @foreach (Order::SHIPPING_METHODS as $method)
                                    <option value="{{ $method }}">{{ __("checkout.methods.$method.title") }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </label>

                    <label class="flex flex-col gap-1">
                        <span class="{{ $label }}">{{ __('admin.orders.city') }}</span>
                        <x-filament::input.wrapper :disabled="! $editable">
                            <x-filament::input.select wire:model="delivery.city" :disabled="! $editable">
                                <option value="">{{ __('admin.orders.not_set') }}</option>
                                @foreach (Order::CITIES as $city)
                                    <option value="{{ $city }}">{{ __("checkout.cities.$city") }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </label>

                    @foreach (['address', 'apartment'] as $field)
                        <label class="flex flex-col gap-1">
                            <span class="{{ $label }}">{{ __("admin.orders.$field") }}</span>
                            <x-filament::input.wrapper :valid="! $errors->has('delivery.'.$field)" :disabled="! $editable">
                                <x-filament::input type="text" wire:model="delivery.{{ $field }}" :disabled="! $editable" />
                            </x-filament::input.wrapper>
                        </label>
                    @endforeach

                    <label class="flex flex-col gap-1">
                        <span class="flex items-center justify-between {{ $label }}">
                            {{ __('admin.orders.shipping_fee') }}
                            @if ($editable)
                                <button type="button" wire:click="recalculateShipping" class="flex items-center gap-1 text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">
                                    <x-filament::icon icon="heroicon-m-arrow-path" class="size-3.5" />{{ __('admin.orders.recalculate_shipping') }}
                                </button>
                            @endif
                        </span>
                        <x-filament::input.wrapper suffix="₾" :valid="! $errors->has('shippingFee')" :disabled="! $editable">
                            <x-filament::input type="number" step="0.01" min="0" wire:model.live.debounce.500ms="shippingFee" :disabled="! $editable" />
                        </x-filament::input.wrapper>
                        @error('shippingFee') <span class="{{ $error }}">{{ $message }}</span> @enderror
                    </label>

                    <label class="flex flex-col gap-1">
                        <span class="{{ $label }}">{{ __('admin.orders.note') }}</span>
                        <x-filament::input.wrapper :disabled="! $editable">
                            <textarea wire:model="delivery.note" rows="2" @disabled(! $editable) class="block w-full resize-y border-none bg-transparent px-3 py-1.5 text-sm text-gray-950 outline-none focus:ring-0 dark:text-white"></textarea>
                        </x-filament::input.wrapper>
                    </label>
                </div>
            </x-filament::section>

            @if ($editable)
                <x-filament::button wire:click="save" icon="heroicon-o-check" size="lg" class="w-full">
                    {{ __('admin.orders.save') }}
                </x-filament::button>
            @endif
        </div>
    </div>
</x-filament-panels::page>
