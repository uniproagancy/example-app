@php
    $summary = $this->summary;
    $money = fn ($value) => App\Support\Catalog::money($value);
    $label = 'text-xs font-medium text-gray-500 dark:text-gray-400';
    $card = 'flex flex-col gap-1 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10';
@endphp

<x-filament-panels::page>
    <div class="flex flex-col gap-4">
        <x-filament::section compact>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @if ($this->isManager())
                    <label class="flex flex-col gap-1">
                        <span class="{{ $label }}">{{ __('courier.report.courier') }}</span>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="courier">
                                <option value="">{{ __('courier.report.all_couriers') }}</option>
                                @foreach ($this->couriers as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </label>
                @endif
                <label class="flex flex-col gap-1">
                    <span class="{{ $label }}">{{ __('courier.report.period') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="period">
                            @foreach (App\Filament\Courier\Pages\Report::PERIODS as $option)
                                <option value="{{ $option }}">{{ __("courier.report.periods.$option") }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
                @if ($period === 'custom')
                    <label class="flex flex-col gap-1">
                        <span class="{{ $label }}">{{ __('courier.report.from') }}</span>
                        <x-filament::input.wrapper>
                            <x-filament::input type="date" wire:model.live="from" />
                        </x-filament::input.wrapper>
                    </label>
                    <label class="flex flex-col gap-1">
                        <span class="{{ $label }}">{{ __('courier.report.to') }}</span>
                        <x-filament::input.wrapper>
                            <x-filament::input type="date" wire:model.live="to" />
                        </x-filament::input.wrapper>
                    </label>
                @endif
            </div>
        </x-filament::section>

        <div class="grid grid-cols-2 gap-3">
            <div class="{{ $card }}">
                <span class="{{ $label }}">{{ __('courier.report.completed') }}</span>
                <span class="text-2xl font-bold text-success-600 dark:text-success-400">{{ $summary['completed'] }}</span>
            </div>
            <div class="{{ $card }}">
                <span class="{{ $label }}">{{ __('courier.report.returned') }}</span>
                <span class="text-2xl font-bold text-danger-600 dark:text-danger-400">{{ $summary['returned'] }}</span>
            </div>
        </div>

        <x-filament::section :heading="__('courier.report.cash')" :description="__('courier.report.cash_help', ['count' => $summary['cash_orders']])">
            <div class="flex flex-col divide-y divide-gray-100 text-sm dark:divide-white/5">
                <div class="flex items-center justify-between py-2.5">
                    <span>{{ __('courier.report.products') }}</span>
                    <span class="text-base font-semibold text-gray-950 dark:text-white">{{ $money($summary['products']) }}</span>
                </div>
                <div class="flex items-center justify-between py-2.5">
                    <span>{{ __('courier.report.delivery') }}</span>
                    <span class="text-base font-semibold text-gray-950 dark:text-white">{{ $money($summary['delivery']) }}</span>
                </div>
                <div class="flex items-center justify-between py-2.5">
                    <span class="font-semibold">{{ __('courier.report.total') }}</span>
                    <span class="text-xl font-bold text-primary-600 dark:text-primary-400">{{ $money($summary['total']) }}</span>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section :heading="__('courier.report.orders')" compact>
            @forelse ($this->orders as $order)
                <div wire:key="report-{{ $order->id }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 py-2.5 text-sm last:border-0 dark:border-white/5">
                    <div class="flex flex-col">
                        <span class="font-semibold text-gray-950 dark:text-white">{{ $order->number }} · {{ trim($order->first_name.' '.$order->last_name) }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ ($order->completed_at ?? $order->returned_at)?->format('d.m.Y H:i') }} · {{ $order->paymentLabel() }}
                            @if ($this->isManager() && $order->courier_id) · {{ $this->couriers[$order->courier_id] ?? '' }}@endif
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-semibold">{{ $money($order->total) }}</span>
                        <x-filament::badge :color="App\Filament\Resources\Orders\OrderResource::statusColor($order->status)">{{ __("account.orders.statuses.{$order->status}") }}</x-filament::badge>
                    </div>
                </div>
            @empty
                <p class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('courier.report.none') }}</p>
            @endforelse
        </x-filament::section>
    </div>
</x-filament-panels::page>
