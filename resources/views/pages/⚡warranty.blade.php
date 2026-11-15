<?php

use App\Models\Order;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public string $number = '';

    public function mount(string $number): void
    {
        $order = Order::query()->where('number', $number)->firstOrFail();

        abort_unless($order->warrantyVisibleToVisitor(), 404);

        $this->number = $order->number;
    }

    public function render()
    {
        $order = Order::query()->with(['items.product.coverImage', 'items.order'])->where('number', $this->number)->firstOrFail();

        return $this->view(['order' => $order])->title(__('warranty.page_title', ['number' => $order->number]));
    }
};
?>

@php
    use App\Models\Page;
    use App\Support\Company;
    use App\Support\Site;

    $company = Company::details();
    $conditionsUrl = Page::link('warranty');
    $stateTone = [
        'active' => 'bg-[#E7F7EE] text-[#14804A]',
        'expired' => 'bg-[#F3F5F9] text-muted',
        'pending' => 'bg-[#EEF4FF] text-brand',
    ];
@endphp

<main class="mx-auto max-w-[820px] px-4 py-10 sm:px-8 sm:py-14 print:max-w-none print:p-0">
    @unless ($order->warrantyIssued())
        <div role="status" class="mb-4 rounded-2xl bg-[#FFF6E5] px-5 py-3.5 text-sm font-bold text-[#B36B00] print:hidden">{{ __('warranty.preview_note') }}</div>
    @endunless

    <div class="flex flex-col gap-6 rounded-[28px] border border-line bg-white p-6 sm:p-10 print:rounded-none print:border-0 print:p-0">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-tint text-brand">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 6v6c0 4.5 3.4 8 8 9 4.6-1 8-4.5 8-9V6z"/><path d="m9 12 2 2 4-4"/></svg>
                </span>
                <div class="flex flex-col gap-0.5">
                    <h1 class="m-0 text-[22px] font-extrabold tracking-[0.3px] sm:text-[26px]">{{ __('warranty.title') }}</h1>
                    <span class="text-sm text-muted">{{ __('warranty.order') }} #{{ $order->number }}</span>
                </div>
            </div>
            <button type="button" x-on:click="window.print()" class="inline-flex h-10 items-center gap-2 rounded-full border border-line-mid px-4 text-sm font-bold text-navy hover:border-brand hover:text-brand print:hidden">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v7H6z"/></svg>
                {{ __('warranty.print') }}
            </button>
        </div>

        <div class="grid grid-cols-1 gap-4 rounded-[18px] bg-field px-5 py-[18px] text-sm sm:grid-cols-3">
            <div class="flex flex-col gap-1">
                <span class="text-muted">{{ __('warranty.purchased') }}</span>
                <span class="font-bold">{{ $order->created_at->format('d.m.Y') }}</span>
            </div>
            <div class="flex flex-col gap-1">
                <span class="text-muted">{{ __('warranty.buyer') }}</span>
                <span class="font-bold">{{ trim($order->first_name.' '.$order->last_name) }}</span>
            </div>
            <div class="flex flex-col gap-1">
                <span class="text-muted">{{ __('warranty.seller') }}</span>
                <span class="font-bold">{{ $company['name'] }}</span>
                @if ($company['code'])
                    <span class="text-xs text-muted">{{ __('warranty.code', ['code' => $company['code']]) }}</span>
                @endif
            </div>
        </div>

        <div class="flex flex-col">
            @foreach ($order->items as $item)
                @php($state = $item->warrantyState())
                @php($endsAt = $item->warrantyEndsAt())
                <div wire:key="warranty-item-{{ $item->id }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-line-soft py-4 first:border-t-0 first:pt-0">
                    <span class="grid size-14 shrink-0 place-items-center overflow-hidden rounded-[14px] bg-[#F3F6FB] print:hidden">
                        @if ($image = $item->product?->coverImageUrl())
                            <img src="{{ $image }}" alt="" class="size-full object-contain" loading="lazy">
                        @endif
                    </span>
                    <span class="flex min-w-0 flex-1 basis-[220px] flex-col gap-0.5">
                        <span class="text-sm font-bold text-navy">{{ $item->name }}</span>
                        <span class="text-xs text-muted">
                            {{ __('warranty.quantity', ['count' => $item->quantity]) }}@if ($item->product?->sku) · {{ $item->product->sku }}@endif @if ($item->options) · {{ implode(' · ', (array) $item->options) }}@endif
                        </span>
                        @if ($item->serial_number)
                            <span class="text-xs font-semibold text-navy">{{ __('warranty.serial') }}: {{ $item->serial_number }}</span>
                        @endif
                    </span>
                    <span class="flex shrink-0 flex-col items-start gap-1 sm:items-end">
                        <span class="font-display text-[15px] font-extrabold">{{ $item->warrantyLabel() }}</span>
                        <span class="text-xs text-muted">{{ $endsAt ? __('warranty.until', ['date' => $endsAt->format('d.m.Y')]) : __('warranty.starts_on_delivery') }}</span>
                    </span>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-extrabold {{ $stateTone[$state] }}">{{ __("warranty.states.$state") }}</span>
                </div>
            @endforeach
        </div>

        <p class="m-0 rounded-2xl bg-tint px-5 py-4 text-sm leading-relaxed font-semibold text-navy">
            {{ $order->warranty_starts_at ? __('warranty.started_note', ['date' => $order->warranty_starts_at->format('d.m.Y')]) : __('warranty.pending_note') }}
        </p>

        <div class="flex flex-col gap-3 text-sm leading-relaxed">
            <h2 class="m-0 text-base font-extrabold">{{ __('warranty.how_title') }}</h2>
            <ol class="m-0 flex list-decimal flex-col gap-1.5 pl-5">
                @foreach (__('warranty.how') as $step)
                    <li>{{ $step }}</li>
                @endforeach
            </ol>
            <p class="m-0 text-muted">{{ __('warranty.not_covered') }}</p>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4 text-sm">
            <span class="text-muted">
                {{ __('warranty.contact') }}
                <a href="{{ Site::phoneHref() }}" class="font-bold">{{ Site::phone() }}</a>@if (Site::email()) · <a href="mailto:{{ Site::email() }}" class="font-bold">{{ Site::email() }}</a>@endif
            </span>
            @if ($conditionsUrl)
                <a href="{{ $conditionsUrl }}" wire:navigate class="font-bold print:hidden">{{ __('warranty.conditions') }}</a>
            @endif
        </div>
    </div>
</main>
