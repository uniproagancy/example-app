<?php

namespace App\Filament\Courier\Pages;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Facebook\Pixel;
use App\Support\Catalog;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Deliveries extends Page
{
    use WithFileUploads;

    public const STATUS = 'confirmed';

    public const LIMIT = 100;

    public const PHOTO_DISK = 'local';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $slug = 'deliveries';

    protected string $view = 'filament.courier.deliveries';

    /**
     * @var array<string, array{serial: string, months: int|string}>
     */
    public array $items = [];

    /**
     * @var array<string, TemporaryUploadedFile|null>
     */
    public array $photos = [];

    /**
     * @var array<string, bool>
     */
    public array $collected = [];

    #[Url]
    public string $search = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_admin === true || $user->is_courier === true);
    }

    public static function getNavigationLabel(): string
    {
        return __('courier.deliveries.title');
    }

    public function getTitle(): string
    {
        return __('courier.deliveries.title');
    }

    public function mount(): void
    {
        $this->fillItems();
    }

    public function updatedSearch(): void
    {
        unset($this->orders);
        $this->fillItems();
    }

    /**
     * @return Collection<int, Order>
     */
    #[Computed]
    public function orders(): Collection
    {
        $search = trim($this->search);

        return Order::query()
            ->where('status', self::STATUS)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%")))
            ->with('items')
            ->oldest()
            ->limit(self::LIMIT)
            ->get();
    }

    public function saveItem(int $itemId): void
    {
        $item = OrderItem::query()->with('order')->findOrFail($itemId);

        abort_unless($item->order !== null && $item->order->status === self::STATUS, 403);

        $key = 'i'.$item->id;

        $this->validate([
            "items.{$key}.serial" => ['nullable', 'string', 'max:100'],
            "items.{$key}.months" => ['required', 'integer', 'min:1', 'max:120'],
            "photos.{$key}" => ['nullable', 'image', 'max:15360'],
        ], [], [
            "items.{$key}.serial" => __('courier.deliveries.serial'),
            "items.{$key}.months" => __('courier.deliveries.months'),
            "photos.{$key}" => __('courier.deliveries.photo'),
        ]);

        $serial = trim((string) ($this->items[$key]['serial'] ?? '')) ?: null;
        $data = ['serial_number' => $serial, 'warranty_months' => (int) $this->items[$key]['months']];
        $photo = $this->photos[$key] ?? null;
        $previous = $item->serial_photo;

        if ($photo instanceof TemporaryUploadedFile) {
            $data['serial_photo'] = $photo->storeAs(
                'serial-photos/'.$item->order_id,
                $item->id.'-'.Str::lower(Str::random(10)).'.'.($photo->guessExtension() ?: 'jpg'),
                self::PHOTO_DISK,
            );
        }

        DB::transaction(function () use ($item, $data, $serial): void {
            $item->update($data);
            $item->order->log('note', __('courier.deliveries.logged', [
                'item' => $item->name,
                'serial' => $serial ?? '—',
                'months' => $data['warranty_months'],
                'courier' => (string) auth()->user()?->name,
            ]));
        });

        if (isset($data['serial_photo']) && filled($previous)) {
            Storage::disk(self::PHOTO_DISK)->delete($previous);
        }

        unset($this->photos[$key], $this->orders);

        Notification::make()->title(__('courier.deliveries.saved'))->success()->send();
    }

    public function markDelivered(int $orderId): void
    {
        $order = Order::query()->findOrFail($orderId);

        abort_unless($order->status === self::STATUS, 403);

        $courier = (string) auth()->user()?->name;
        $collected = ! $order->isPaid() && ($this->collected['o'.$order->id] ?? false);

        DB::transaction(function () use ($order, $courier, $collected): void {
            $order->update(['status' => 'completed']);
            $order->log('status', __('courier.deliveries.delivered_log', ['courier' => $courier]), self::STATUS, 'completed');

            if ($collected) {
                $order->setPaymentStatus(Order::PAYMENT_PAID, __('courier.deliveries.collected_log', ['courier' => $courier, 'amount' => Catalog::money($order->total)]));
            }
        });

        if (app(Pixel::class)->reportSale($order)) {
            $order->log('note', __('admin.orders.purchase_reported'));
        }

        unset($this->collected['o'.$order->id], $this->orders);

        Notification::make()->title(__('courier.deliveries.delivered', ['number' => $order->number]))->success()->send();
    }

    protected function fillItems(): void
    {
        foreach ($this->orders as $order) {
            foreach ($order->items as $item) {
                $this->items['i'.$item->id] ??= [
                    'serial' => (string) $item->serial_number,
                    'months' => $item->warrantyMonths(),
                ];
            }
        }
    }
}
