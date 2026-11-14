@php
    $report = $this->report;
    $summary = $this->summary;
    $previous = $this->previousSummary;
    $money = fn ($value) => App\Support\Catalog::money($value);
    $cards = [
        ['label' => __('admin.reports.revenue'), 'value' => $money($summary['revenue']), 'change' => $report->change($summary['revenue'], $previous['revenue']), 'help' => __('admin.reports.revenue_help')],
        ['label' => __('admin.reports.orders'), 'value' => number_format($summary['valid'], 0, '.', ' '), 'change' => $report->change($summary['valid'], $previous['valid']), 'help' => __('admin.reports.orders_help', ['all' => $summary['orders']])],
        ['label' => __('admin.reports.average'), 'value' => $money($summary['average']), 'change' => $report->change($summary['average'], $previous['average']), 'help' => null],
        ['label' => __('admin.reports.paid'), 'value' => $money($summary['paid']), 'change' => $report->change($summary['paid'], $previous['paid']), 'help' => __('admin.reports.paid_help')],
        ['label' => __('admin.reports.items'), 'value' => number_format($summary['items'], 0, '.', ' '), 'change' => $report->change($summary['items'], $previous['items']), 'help' => null],
        ['label' => __('admin.reports.cancelled'), 'value' => $summary['cancelled'].' · '.$summary['cancelled_share'].'%', 'change' => null, 'help' => __('admin.reports.completed', ['count' => $summary['completed']])],
    ];
    $tables = [
        ['title' => __('admin.reports.by_payment'), 'rows' => $report->breakdown('payment_method'), 'label' => fn ($value) => $this->paymentLabel($value)],
        ['title' => __('admin.reports.by_status'), 'rows' => $report->breakdown('status'), 'label' => fn ($value) => $this->statusLabel($value)],
        ['title' => __('admin.reports.by_source'), 'rows' => $report->breakdown('source'), 'label' => fn ($value) => $this->sourceLabel($value)],
        ['title' => __('admin.reports.by_city'), 'rows' => $report->breakdown('city'), 'label' => fn ($value) => $this->cityLabel($value)],
    ];
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-wrap items-end gap-4">
            <label class="grid gap-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ __('admin.reports.period') }}
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="period">
                        @foreach (App\Filament\Pages\Reports::PERIODS as $option)
                            <option value="{{ $option }}">{{ __("admin.reports.periods.$option") }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label class="grid gap-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ __('admin.common.starts_at') }}
                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model.live="from" />
                </x-filament::input.wrapper>
            </label>
            <label class="grid gap-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ __('admin.common.ends_at') }}
                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model.live="to" />
                </x-filament::input.wrapper>
            </label>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('admin.reports.compared', ['from' => $report->previous()->from->format('d.m.Y'), 'to' => $report->previous()->to->format('d.m.Y')]) }}
            </p>
        </div>
    </x-filament::section>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($cards as $card)
            <x-filament::section>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $card['value'] }}</p>
                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                    @if ($card['change'] !== null)
                        <span @class(['font-semibold', 'text-success-600 dark:text-success-400' => $card['change'] >= 0, 'text-danger-600 dark:text-danger-400' => $card['change'] < 0])>
                            {{ $card['change'] >= 0 ? '▲' : '▼' }} {{ abs($card['change']) }}%
                        </span>
                    @endif
                    @if ($card['help'])
                        <span class="text-gray-500 dark:text-gray-400">{{ $card['help'] }}</span>
                    @endif
                </div>
            </x-filament::section>
        @endforeach
    </div>

    @livewire(App\Filament\Widgets\SalesChart::class, ['from' => $report->from->toDateString(), 'to' => $report->to->toDateString()], key('sales-chart-'.$report->from->toDateString().'-'.$report->to->toDateString()))

    <div class="grid gap-4 xl:grid-cols-2">
        <x-filament::section :heading="__('admin.reports.top_products')">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400">
                        <th class="py-2 pr-2 font-medium">{{ __('admin.reports.product') }}</th>
                        <th class="py-2 px-2 text-right font-medium">{{ __('admin.reports.quantity') }}</th>
                        <th class="py-2 pl-2 text-right font-medium">{{ __('admin.reports.revenue') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($report->topProducts() as $row)
                        <tr>
                            <td class="py-2 pr-2 text-gray-950 dark:text-white">
                                @if ($row['product_id'])
                                    <a href="{{ App\Filament\Resources\Products\ProductResource::getUrl('edit', ['record' => $row['product_id']]) }}" class="hover:underline">{{ $row['name'] }}</a>
                                @else
                                    {{ $row['name'] }}
                                @endif
                            </td>
                            <td class="py-2 px-2 text-right">{{ $row['quantity'] }}</td>
                            <td class="py-2 pl-2 text-right font-medium whitespace-nowrap">{{ $money($row['revenue']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-4 text-center text-gray-500">{{ __('admin.reports.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>

        <x-filament::section :heading="__('admin.reports.top_categories')">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400">
                        <th class="py-2 pr-2 font-medium">{{ __('admin.reports.category') }}</th>
                        <th class="py-2 px-2 text-right font-medium">{{ __('admin.reports.quantity') }}</th>
                        <th class="py-2 pl-2 text-right font-medium">{{ __('admin.reports.revenue') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($report->topCategories() as $row)
                        <tr>
                            <td class="py-2 pr-2 text-gray-950 dark:text-white">{{ $row['name'] }}</td>
                            <td class="py-2 px-2 text-right">{{ $row['quantity'] }}</td>
                            <td class="py-2 pl-2 text-right font-medium whitespace-nowrap">{{ $money($row['revenue']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-4 text-center text-gray-500">{{ __('admin.reports.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>

        @foreach ($tables as $table)
            <x-filament::section :heading="$table['title']">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th class="py-2 pr-2 font-medium"></th>
                            <th class="py-2 px-2 text-right font-medium">{{ __('admin.reports.orders') }}</th>
                            <th class="py-2 pl-2 text-right font-medium">{{ __('admin.reports.revenue') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($table['rows'] as $row)
                            <tr>
                                <td class="py-2 pr-2 text-gray-950 dark:text-white">{{ ($table['label'])($row['value']) }}</td>
                                <td class="py-2 px-2 text-right">{{ $row['orders'] }}</td>
                                <td class="py-2 pl-2 text-right font-medium whitespace-nowrap">{{ $money($row['revenue']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-4 text-center text-gray-500">{{ __('admin.reports.empty') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
