<?php

namespace App\Filament\Courier\Pages;

use App\Models\Order;
use App\Models\OrderItem;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class Pickups extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'pickups';

    protected string $view = 'filament.courier.pickups';

    #[Url]
    public ?string $day = null;

    public static function canAccess(): bool
    {
        return Deliveries::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return __('courier.pickups.title');
    }

    public function getTitle(): string
    {
        return __('courier.pickups.title');
    }

    public function mount(): void
    {
        $this->day = $this->validDay($this->day);
    }

    public function updatedDay(): void
    {
        $this->day = $this->validDay($this->day);
        unset($this->items, $this->groups);
    }

    /**
     * @return Collection<int, OrderItem>
     */
    #[Computed]
    public function items(): Collection
    {
        return OrderItem::query()
            ->whereNotNull('supplier_ordered_at')
            ->whereDate('supplier_ordered_at', $this->day)
            ->whereHas('order', fn ($query) => $query->whereNotIn('status', Order::LOST_STATUSES))
            ->with(['order', 'product.offers.supplier', 'pickedUpBy'])
            ->orderBy('pickup_warehouse')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<string, Collection<int, OrderItem>>
     */
    #[Computed]
    public function groups(): Collection
    {
        return $this->items->groupBy(fn (OrderItem $item): string => $this->placeOf($item));
    }

    public function togglePickup(int $itemId): void
    {
        $item = OrderItem::query()->with('order')->findOrFail($itemId);

        abort_unless($item->supplier_ordered_at !== null && $item->order !== null && ! $item->order->isLost(), 403);

        $picking = $item->picked_up_at === null;

        $item->update($picking
            ? ['picked_up_at' => now(), 'picked_up_by' => auth()->id()]
            : ['picked_up_at' => null, 'picked_up_by' => null]);

        $item->order->log('note', __($picking ? 'courier.pickups.picked_log' : 'courier.pickups.unpicked_log', [
            'item' => $item->name,
            'courier' => (string) auth()->user()?->name,
        ]));

        unset($this->items, $this->groups);

        Notification::make()
            ->title(__($picking ? 'courier.pickups.picked' : 'courier.pickups.unpicked', ['item' => $item->name]))
            ->success()
            ->send();
    }

    public function supplierOf(OrderItem $item): ?string
    {
        return $item->product?->offers->sortBy('cost_price')->first()?->supplier?->name;
    }

    public function supplierCodeOf(OrderItem $item): ?string
    {
        return $item->product?->offers->sortBy('cost_price')->first()?->external_id;
    }

    protected function placeOf(OrderItem $item): string
    {
        return $item->pickup_warehouse ?: ($this->supplierOf($item) ?: __('courier.pickups.no_place'));
    }

    protected function validDay(?string $value): string
    {
        try {
            return filled($value) ? Carbon::parse($value)->toDateString() : today()->toDateString();
        } catch (\Throwable) {
            return today()->toDateString();
        }
    }
}
