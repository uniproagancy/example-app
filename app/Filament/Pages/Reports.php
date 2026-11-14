<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\AdminOnlyPage;
use App\Models\PaymentMethod;
use App\Services\Orders\SalesReport;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use UnitEnum;

class Reports extends Page
{
    use AdminOnlyPage;

    public const PERIODS = ['7', '30', '90', 'month', 'previous_month', 'year', 'custom'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'reports';

    protected string $view = 'filament.pages.reports';

    #[Url]
    public string $period = '30';

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.sales');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.reports.label');
    }

    public function getTitle(): string
    {
        return __('admin.reports.label');
    }

    public function mount(): void
    {
        if (! in_array($this->period, self::PERIODS, true)) {
            $this->period = '30';
        }

        $this->from ??= $this->report->from->toDateString();
        $this->to ??= $this->report->to->toDateString();
    }

    public function updatedPeriod(): void
    {
        if (! in_array($this->period, self::PERIODS, true)) {
            $this->period = '30';
        }

        if ($this->period !== 'custom') {
            unset($this->report);
            $this->from = $this->report->from->toDateString();
            $this->to = $this->report->to->toDateString();
        }

        $this->refreshReport();
    }

    public function updatedFrom(): void
    {
        $this->period = 'custom';
        $this->refreshReport();
    }

    public function updatedTo(): void
    {
        $this->period = 'custom';
        $this->refreshReport();
    }

    protected function refreshReport(): void
    {
        unset($this->report, $this->summary, $this->previousSummary);
    }

    #[Computed]
    public function report(): SalesReport
    {
        return SalesReport::forPeriod($this->period, $this->from, $this->to);
    }

    #[Computed]
    public function summary(): array
    {
        return $this->report->summary();
    }

    #[Computed]
    public function previousSummary(): array
    {
        return $this->report->previous()->summary();
    }

    public function paymentLabel(string $code): string
    {
        return PaymentMethod::labelFor($code) ?? ($code !== '' ? $code : __('admin.common.none'));
    }

    public function statusLabel(string $status): string
    {
        return __("account.statuses.$status");
    }

    public function sourceLabel(string $source): string
    {
        return __("admin.reports.sources.$source");
    }

    public function cityLabel(string $city): string
    {
        return $city !== '' ? __("checkout.cities.$city") : __('admin.common.none');
    }

    public function ordersUrl(): string
    {
        return OrderResource::getUrl();
    }
}
