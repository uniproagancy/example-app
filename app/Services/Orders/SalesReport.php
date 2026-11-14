<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Support\CategoryTree;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesReport
{
    public const CANCELLED = 'cancelled';

    public function __construct(public CarbonImmutable $from, public CarbonImmutable $to)
    {
        $this->from = $from->startOfDay();
        $this->to = $to->endOfDay();
    }

    public static function forPeriod(string $period, ?string $from = null, ?string $to = null): self
    {
        $today = CarbonImmutable::today();

        return match ($period) {
            '7' => new self($today->subDays(6), $today),
            '90' => new self($today->subDays(89), $today),
            'month' => new self($today->startOfMonth(), $today),
            'previous_month' => new self($today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()),
            'year' => new self($today->startOfYear(), $today),
            'custom' => static::custom($from, $to),
            default => new self($today->subDays(29), $today),
        };
    }

    protected static function custom(?string $from, ?string $to): self
    {
        $start = rescue(fn () => CarbonImmutable::parse((string) $from), null, false) ?? CarbonImmutable::today()->subDays(29);
        $end = rescue(fn () => CarbonImmutable::parse((string) $to), null, false) ?? CarbonImmutable::today();

        return $start->greaterThan($end) ? new self($end, $start) : new self($start, $end);
    }

    public function previous(): self
    {
        $days = (int) $this->from->diffInDays($this->to->startOfDay()) + 1;

        return new self($this->from->subDays($days), $this->from->subDay());
    }

    public function orders(): Builder
    {
        return Order::query()->whereBetween('created_at', [$this->from, $this->to]);
    }

    public function validOrders(): Builder
    {
        return $this->orders()->where('status', '!=', self::CANCELLED);
    }

    public function summary(): array
    {
        $all = (int) $this->orders()->count();
        $valid = (int) $this->validOrders()->count();
        $revenue = round((float) $this->validOrders()->sum('total'), 2);
        $paid = round((float) $this->validOrders()->where('payment_status', Order::PAYMENT_PAID)->sum('total'), 2);
        $items = (int) DB::table('order_items')->whereIn('order_id', $this->validOrders()->select('id'))->sum('quantity');
        $cancelled = $all - $valid;

        return [
            'orders' => $all,
            'valid' => $valid,
            'revenue' => $revenue,
            'paid' => $paid,
            'average' => $valid > 0 ? round($revenue / $valid, 2) : 0.0,
            'items' => $items,
            'cancelled' => $cancelled,
            'cancelled_share' => $all > 0 ? round($cancelled / $all * 100, 1) : 0.0,
            'completed' => (int) $this->orders()->where('status', 'completed')->count(),
        ];
    }

    public function change(float $current, float $previous): ?float
    {
        if ($previous <= 0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }

    public function daily(): Collection
    {
        $rows = $this->validOrders()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as orders, SUM(total) as revenue')
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy(fn ($row) => (string) $row->day);

        return collect(CarbonPeriod::create($this->from, $this->to->startOfDay()))
            ->map(fn ($day) => [
                'day' => $day->format('Y-m-d'),
                'label' => $day->format('d.m'),
                'orders' => (int) ($rows[$day->format('Y-m-d')]->orders ?? 0),
                'revenue' => round((float) ($rows[$day->format('Y-m-d')]->revenue ?? 0), 2),
            ])
            ->values();
    }

    public function topProducts(int $limit = 10): Collection
    {
        return DB::table('order_items')
            ->whereIn('order_id', $this->validOrders()->select('id'))
            ->selectRaw('product_id, MAX(name) as name, SUM(quantity) as quantity, SUM(total) as revenue')
            ->groupBy('product_id')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'product_id' => $row->product_id ? (int) $row->product_id : null,
                'name' => (string) $row->name,
                'quantity' => (int) $row->quantity,
                'revenue' => round((float) $row->revenue, 2),
            ]);
    }

    public function topCategories(int $limit = 10): Collection
    {
        $rows = DB::table('order_items')
            ->whereIn('order_id', $this->validOrders()->select('id'))
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->selectRaw('products.category_id as category_id, SUM(order_items.quantity) as quantity, SUM(order_items.total) as revenue')
            ->groupBy('products.category_id')
            ->get();

        return $rows
            ->groupBy(fn ($row) => $this->rootTitle($row->category_id ? (int) $row->category_id : null))
            ->map(fn (Collection $group, string $title) => [
                'name' => $title,
                'quantity' => (int) $group->sum('quantity'),
                'revenue' => round((float) $group->sum('revenue'), 2),
            ])
            ->sortByDesc('revenue')
            ->take($limit)
            ->values();
    }

    public function breakdown(string $column): Collection
    {
        $revenueColumn = "SUM(CASE WHEN status != 'cancelled' THEN total ELSE 0 END)";

        return $this->orders()
            ->selectRaw("{$column} as value, COUNT(*) as orders, {$revenueColumn} as revenue")
            ->groupBy($column)
            ->orderByDesc('orders')
            ->get()
            ->map(fn ($row) => [
                'value' => (string) ($row->value ?? ''),
                'orders' => (int) $row->orders,
                'revenue' => round((float) $row->revenue, 2),
            ]);
    }

    protected function rootTitle(?int $categoryId): string
    {
        if (! $categoryId || ! ($node = CategoryTree::find($categoryId))) {
            return __('admin.reports.no_category');
        }

        $root = CategoryTree::ancestors($categoryId)->first() ?? $node;

        return (string) $root['title'];
    }
}
