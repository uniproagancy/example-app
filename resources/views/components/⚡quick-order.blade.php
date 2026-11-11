<?php

use App\Models\Order;
use App\Services\Facebook\Pixel;
use App\Models\PaymentMethod;
use App\Services\Payments\BankHandoff;
use App\Support\Catalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $productId = 0;

    public string $name = '';

    public string $phone = '';

    public string $payment = '';

    public bool $sent = false;

    public function mount(int $productId): void
    {
        $this->productId = $productId;

        if ($user = auth()->user()) {
            $this->name = $user->name;
            $this->phone = (string) $user->phone;
        }

        $this->payment = (string) $this->methods->first()?->code;
    }

    #[Computed]
    public function methods(): Collection
    {
        return PaymentMethod::forCheckout((float) (Catalog::find($this->productId)['price'] ?? 0), quick: true);
    }

    public function submit(int $quantity = 1, array $options = []): void
    {
        $product = Catalog::find($this->productId);

        abort_unless($product && $product['inStock'], 404);

        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9\s\-()]{9,20}$/'],
            'payment' => ['required', Rule::in($this->methods->pluck('code')->all())],
        ]);

        $throttleKey = 'quick-order:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            throw ValidationException::withMessages([
                'phone' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        RateLimiter::hit($throttleKey, 600);

        $quantity = max(1, min($quantity, 10, $product['stock']));
        $resolved = Catalog::resolveOptions($product, array_map('intval', $options));

        $total = $resolved['price'] * $quantity;

        $order = DB::transaction(function () use ($product, $resolved, $quantity, $total): Order {
            $order = Order::query()->create([
                'number' => Order::generateNumber(),
                'user_id' => auth()->user()?->id,
                'status' => 'new',
                'source' => 'quick',
                'first_name' => $this->name,
                'phone' => $this->phone,
                'email' => auth()->user()?->email,
                'payment_method' => $this->payment,
                'payment_status' => Order::PAYMENT_PENDING,
                'subtotal' => $total,
                'discount' => 0,
                'shipping' => 0,
                'total' => $total,
                'locale' => app()->getLocale(),
            ]);

            $order->items()->create([
                'product_id' => $product['id'],
                'name' => $product['name'],
                'options' => $resolved['labels'] ?: null,
                'unit_price' => $resolved['price'],
                'quantity' => $quantity,
                'total' => $total,
            ]);

            return $order;
        });

        $pixel = app(Pixel::class);
        $pixel->snapshotForOrder($order);
        $lead = $pixel->quickLead($order);

        $order->rememberInSession();
        $step = app(BankHandoff::class)->begin($order);

        if ($step['type'] !== 'none') {
            $pixel->flash($lead);
        } else {
            $this->dispatch('pixel', $lead);
        }

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

        $this->sent = true;
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => __('product.quick.name'),
            'phone' => __('product.quick.phone'),
            'payment' => __('product.quick.payment'),
        ];
    }
};
?>

@php
    $fieldClass = 'h-12 min-w-0 rounded-xl border bg-white/6 px-3.5 text-sm text-white outline-0 placeholder:text-white/35 focus:border-sky focus:bg-white/10';
@endphp

<form
    x-on:submit.prevent="$wire.submit(qty, optionIds())"
    class="relative flex flex-col gap-4 overflow-hidden rounded-[22px] bg-navy p-[22px] text-white"
>
    <div class="pointer-events-none absolute -top-20 -right-[60px] size-[220px] rounded-full bg-[radial-gradient(circle,rgba(20,180,255,.3),transparent_65%)]"></div>
    <span class="relative text-[17px] font-extrabold text-white">{{ __('product.quick.title') }}</span>

    @if ($sent)
        <div role="status" class="relative flex items-center gap-3 rounded-xl bg-[#1FB866]/15 p-3.5 text-sm font-semibold text-[#7BE3A8]">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg>
            {{ __('product.quick.sent') }}
        </div>
    @else
        <div class="relative grid grid-cols-1 gap-2.5 sm:grid-cols-2">
            <label class="flex min-w-0 flex-col gap-1.5">
                <span class="text-xs text-faint">{{ __('product.quick.name') }}</span>
                <input wire:model="name" autocomplete="name" class="{{ $fieldClass }} {{ $errors->has('name') ? 'border-sale' : 'border-white/14' }}">
                @error('name') <span class="text-xs text-[#FF8A8E]">{{ $message }}</span> @enderror
            </label>
            <label class="flex min-w-0 flex-col gap-1.5">
                <span class="text-xs text-faint">{{ __('product.quick.phone') }}</span>
                <input wire:model="phone" type="tel" autocomplete="tel" placeholder="5__ __ __ __" class="{{ $fieldClass }} {{ $errors->has('phone') ? 'border-sale' : 'border-white/14' }}">
                @error('phone') <span class="text-xs text-[#FF8A8E]">{{ $message }}</span> @enderror
            </label>
        </div>

        <span class="relative flex items-center gap-2 text-sm font-extrabold text-white">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#14B4FF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/></svg>
            {{ __('product.quick.payment') }}
        </span>

        <div class="relative flex flex-col gap-2" x-data="{ payment: $wire.entangle('payment').live }">
            @foreach ($this->methods as $method)
                <button type="button" wire:key="quick-method-{{ $method->code }}" x-on:click="payment = '{{ $method->code }}'" class="flex min-h-12 w-full items-center gap-3 rounded-xl border-[1.5px] px-3.5 py-2 text-left text-sm text-white transition" :class="payment === '{{ $method->code }}' ? 'border-sky bg-sky/12' : 'border-white/14 bg-white/4'">
                    <span class="grid size-[18px] shrink-0 place-items-center rounded-full border-[1.5px]" :class="payment === '{{ $method->code }}' ? 'border-sky' : 'border-white/35'">
                        <span class="size-2 rounded-full" :class="payment === '{{ $method->code }}' ? 'bg-sky' : 'bg-transparent'"></span>
                    </span>
                    <span class="min-w-0 flex-1">{{ $method->name }}</span>
                    @if ($logo = $method->logoUrl())
                        <span class="flex h-7 shrink-0 items-center rounded-md bg-white px-1.5"><img src="{{ $logo }}" alt="" class="h-5 max-w-[64px] object-contain" loading="lazy"></span>
                    @endif
                </button>
            @endforeach
            @error('payment') <span class="text-xs text-[#FF8A8E]">{{ $message }}</span> @enderror
        </div>

        <button type="submit" wire:loading.attr="disabled" class="relative flex h-[52px] items-center justify-center gap-2 rounded-[14px] bg-[#1FB866] text-[15px] font-extrabold text-white hover:bg-ok disabled:opacity-60">
            {{ __('product.quick.submit') }}
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
        </button>
    @endif
</form>
