<?php

namespace App\Filament\Widgets;

use App\Services\Orders\SalesReport;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class SalesChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $maxHeight = '320px';

    public string $from = '';

    public string $to = '';

    public function getHeading(): string
    {
        return __('admin.reports.daily');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $days = (new SalesReport(CarbonImmutable::parse($this->from), CarbonImmutable::parse($this->to)))->daily();

        return [
            'labels' => $days->pluck('label')->all(),
            'datasets' => [
                [
                    'type' => 'bar',
                    'label' => __('admin.reports.revenue'),
                    'data' => $days->pluck('revenue')->all(),
                    'backgroundColor' => 'rgba(11, 99, 246, 0.75)',
                    'borderRadius' => 4,
                    'yAxisID' => 'y',
                ],
                [
                    'type' => 'line',
                    'label' => __('admin.reports.orders'),
                    'data' => $days->pluck('orders')->all(),
                    'borderColor' => '#F5A524',
                    'backgroundColor' => '#F5A524',
                    'tension' => 0.3,
                    'yAxisID' => 'orders',
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['beginAtZero' => true, 'position' => 'left'],
                'orders' => ['beginAtZero' => true, 'position' => 'right', 'grid' => ['drawOnChartArea' => false], 'ticks' => ['precision' => 0]],
            ],
            'plugins' => ['legend' => ['display' => true]],
        ];
    }
}
