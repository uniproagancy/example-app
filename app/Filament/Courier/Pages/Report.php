<?php

namespace App\Filament\Courier\Pages;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class Report extends Page
{
    public const PERIODS = ['today', 'yesterday', '7', '30', 'month', 'previous_month', 'custom'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'report';

    protected string $view = 'filament.courier.report';

    #[Url]
    public string $period = 'today';

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    #[Url]
    public ?string $courier = null;

    public static function canAccess(): bool
    {
        return Deliveries::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return __('courier.report.title');
    }

    public function getTitle(): string
    {
        return __('courier.report.title');
    }

    public function mount(): void
    {
        if (! in_array($this->period, self::PERIODS, true)) {
            $this->period = 'today';
        }
    }

    public function isManager(): bool
    {
        return auth()->user()?->is_admin === true;
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function couriers(): Collection
    {
        return User::query()->where('is_courier', true)->orderBy('name')->pluck('name', 'id');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    #[Computed]
    public function range(): array
    {
        $today = today();

        return match ($this->period) {
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()->endOfDay()],
            '7' => [$today->copy()->subDays(6), $today->copy()->endOfDay()],
            '30' => [$today->copy()->subDays(29), $today->copy()->endOfDay()],
            'month' => [$today->copy()->startOfMonth(), $today->copy()->endOfDay()],
            'previous_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'custom' => [
                $this->date($this->from) ?? $today->copy(),
                ($this->date($this->to) ?? $today->copy())->endOfDay(),
            ],
            default => [$today->copy(), $today->copy()->endOfDay()],
        };
    }

    /**
     * @return array{completed: int, returned: int, products: float, delivery: float, total: float, cash_orders: int}
     */
    #[Computed]
    public function summary(): array
    {
        $cash = $this->completed()->where('payment_method', PaymentMethod::CASH);
        $total = round((float) (clone $cash)->sum('total'), 2);
        $delivery = round((float) (clone $cash)->sum('shipping'), 2);

        return [
            'completed' => $this->completed()->count(),
            'returned' => $this->returned()->count(),
            'cash_orders' => (clone $cash)->count(),
            'products' => round($total - $delivery, 2),
            'delivery' => $delivery,
            'total' => $total,
        ];
    }

    /**
     * @return Collection<int, Order>
     */
    #[Computed]
    public function orders(): Collection
    {
        return $this->completed()->get()
            ->concat($this->returned()->get())
            ->sortByDesc(fn (Order $order) => $order->completed_at ?? $order->returned_at)
            ->values();
    }

    protected function completed(): Builder
    {
        return $this->mine()->where('status', 'completed')->whereBetween('completed_at', $this->range);
    }

    protected function returned(): Builder
    {
        return $this->mine()->where('status', 'returned')->whereBetween('returned_at', $this->range);
    }

    protected function mine(): Builder
    {
        $query = Order::query();

        if (! $this->isManager()) {
            return $query->where('courier_id', auth()->id());
        }

        return ctype_digit((string) $this->courier)
            ? $query->where('courier_id', (int) $this->courier)
            : $query->whereNotNull('courier_id');
    }

    protected function date(?string $value): ?Carbon
    {
        try {
            return filled($value) ? Carbon::parse($value)->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
