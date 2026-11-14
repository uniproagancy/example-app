<?php

use App\Models\Order;
use App\Services\Facebook\Pixel;
use App\Services\Payments\BankHandoff;
use App\Services\Payments\PaymentCheck;
use App\Support\Site;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public string $number = '';

    #[Locked]
    public bool $autoPay = false;

    public ?string $notice = null;

    public function mount(string $number): void
    {
        $order = Order::query()->where('number', $number)->firstOrFail();

        abort_unless($order->mayBePaidByVisitor(), 404);

        $this->number = $order->number;
        $this->notice = session('payment_error') ?? (session('payment_failed') ? __('payments.order.return_failed') : null);
        $this->autoPay = request()->boolean('pay') && ! $order->isPaid() && (bool) $order->paymentMethod?->isPayableOnline();

        if ($event = app(Pixel::class)->browserPurchase($order)) {
            $this->dispatch('pixel', $event);
        }
    }

    public function order(): Order
    {
        return Order::query()->with(['items.product.coverImage', 'paymentMethod.translations'])->where('number', $this->number)->firstOrFail();
    }

    public function pay(): void
    {
        $order = $this->order();
        $step = app(BankHandoff::class)->begin($order);

        match ($step['type']) {
            'redirect' => $this->redirect($step['url']),
            'calculator' => $this->dispatch('bog:installment', amount: $step['amount'], url: $step['url'], bnpl: $step['bnpl']),
            'error' => $this->notice = $step['message'],
            default => null,
        };
    }

    public function refreshStatus(): void
    {
        $order = $this->order();

        if ($order->payment_status === Order::PAYMENT_PROCESSING && Cache::add('payment-check:'.$order->id, true, 60)) {
            app(PaymentCheck::class)->ask($order);
        }
    }

    public function render()
    {
        return $this->view(['order' => $this->order()])->title(__('payments.order.title', ['number' => $this->number]));
    }
};
?>

@php
    use App\Support\Catalog;

    $method = $order->paymentMethod;
    $online = (bool) $method?->isPayableOnline();
    $tone = match ($order->payment_status) {
        'paid' => 'bg-[#E7F7EE] text-[#14804A]',
        'processing' => 'bg-[#FFF6E5] text-[#B36B00]',
        'failed' => 'bg-[#FDECEC] text-sale',
        'refunded' => 'bg-[#F3F5F9] text-muted',
        default => 'bg-[#EEF4FF] text-brand',
    };
@endphp

<main class="mx-auto max-w-[760px] px-4 py-10 sm:px-8 sm:py-14" @if ($order->payment_status === 'processing') wire:poll.20s="refreshStatus" @endif>
    @if ($autoPay)
        <div x-init="$wire.pay()"></div>
    @endif

    <div class="flex flex-col gap-5 rounded-[28px] border border-line bg-white p-6 sm:p-10">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex flex-col gap-1">
                <h1 class="m-0 text-[22px] font-extrabold tracking-[0.3px] sm:text-[26px]">{{ __('payments.order.title', ['number' => $order->number]) }}</h1>
                <span class="text-sm text-muted">{{ $order->created_at->format('d.m.Y H:i') }}</span>
            </div>
            <span class="rounded-full px-3.5 py-1.5 text-xs font-extrabold {{ $tone }}">{{ __('payments.statuses.'.$order->payment_status) }}</span>
        </div>

        @if ($notice)
            <div role="alert" class="rounded-2xl bg-[#FDECEC] px-4 py-3 text-sm font-semibold text-sale">{{ $notice }}</div>
        @endif

        <div class="grid grid-cols-1 gap-3 rounded-[18px] bg-field px-5 py-[18px] text-sm sm:grid-cols-2">
            <div class="flex flex-col gap-1">
                <span class="text-muted">{{ __('payments.order.status') }}</span>
                <span class="font-bold">{{ __('account.orders.statuses.'.$order->status) }}</span>
            </div>
            <div class="flex flex-col gap-1">
                <span class="text-muted">{{ __('payments.order.payment') }}</span>
                <span class="flex items-center gap-2 font-bold">
                    @if ($logo = $method?->logoUrl())
                        <img src="{{ $logo }}" alt="" class="h-5 max-w-[60px] object-contain">
                    @endif
                    {{ $order->paymentLabel() }}
                </span>
            </div>
        </div>

        <div class="flex flex-col">
            <span class="mb-1 text-sm font-extrabold">{{ __('payments.order.items') }}</span>
            @foreach ($order->items as $item)
                <div wire:key="order-item-{{ $item->id }}" class="flex items-center gap-3 border-t border-line-soft py-3">
                    <span class="grid size-14 shrink-0 place-items-center overflow-hidden rounded-[14px] bg-[#F3F6FB]">
                        @if ($image = $item->product?->coverImageUrl())
                            <img src="{{ $image }}" alt="" class="size-full object-contain" loading="lazy">
                        @endif
                    </span>
                    <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                        <span class="truncate text-sm font-bold text-navy">{{ $item->name }}</span>
                        <span class="text-xs text-muted">{{ $item->quantity }} × {{ Catalog::money($item->unit_price) }}@if ($item->options) · {{ implode(' · ', (array) $item->options) }}@endif</span>
                    </span>
                    <span class="font-display text-sm font-extrabold">{{ Catalog::money($item->total) }}</span>
                </div>
            @endforeach
        </div>

        <div class="flex flex-col gap-2 border-t border-line pt-4 text-sm">
            <div class="flex justify-between text-muted"><span>{{ __('payments.order.subtotal') }}</span><span class="font-bold text-navy">{{ Catalog::money($order->subtotal) }}</span></div>
            @if ((float) $order->discount > 0)
                <div class="flex justify-between text-[#14804A]"><span>{{ __('payments.order.discount') }}</span><span class="font-bold">−{{ Catalog::money($order->discount) }}</span></div>
            @endif
            <div class="flex justify-between text-muted"><span>{{ __('payments.order.shipping') }}</span><span class="font-bold text-navy">{{ (float) $order->shipping > 0 ? Catalog::money($order->shipping) : __('payments.order.free') }}</span></div>
            <div class="flex items-baseline justify-between pt-1"><span class="font-extrabold">{{ __('payments.order.total') }}</span><span class="font-display text-[26px] font-extrabold">{{ Catalog::money($order->total) }}</span></div>
        </div>

        @if ($order->isPaid())
            <div class="rounded-2xl bg-[#E7F7EE] px-4 py-3 text-sm font-bold text-[#14804A]">{{ __('payments.order.paid') }}</div>
        @elseif ($order->payment_status === 'processing')
            <div class="rounded-2xl bg-[#FFF6E5] px-4 py-3 text-sm font-semibold text-[#B36B00]">{{ __('payments.order.processing') }}</div>
        @endif

        @if (! $order->isPaid() && $online && $order->status !== 'cancelled')
            <div class="flex flex-col gap-2">
                <button type="button" wire:click="pay" wire:loading.attr="disabled" wire:target="pay" class="flex h-[56px] items-center justify-center gap-2.5 rounded-2xl bg-brand text-[15px] font-extrabold text-white hover:bg-brand-dark disabled:opacity-60">
                    {{ str_contains((string) $method->driver, 'installment') ? __('payments.order.apply') : __('payments.order.pay_now', ['amount' => Catalog::money($order->total)]) }}
                </button>
                <span class="text-center text-xs text-muted">{{ __('payments.order.pay_hint') }}</span>
            </div>
        @elseif (! $order->isPaid())
            <p class="m-0 text-sm text-muted">{{ __('payments.order.offline') }}</p>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4 text-sm">
            <span class="text-muted">{{ __('payments.order.questions') }} <a href="{{ Site::phoneHref() }}" class="font-bold">{{ Site::phone() }}</a></span>
            <span class="flex items-center gap-4">
                <a href="{{ $order->invoiceUrl(app()->getLocale()) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 font-bold">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/></svg>
                    {{ __('invoice.download') }}
                </a>
                <a href="{{ route('home') }}" wire:navigate class="font-bold">{{ __('payments.order.continue') }}</a>
            </span>
        </div>
    </div>

    @if ($method?->driver === 'bog-installment')
        @include('partials.bog-calculator')
    @endif
</main>
