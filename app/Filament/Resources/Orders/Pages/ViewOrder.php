<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Filament\Support\Operators;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OrderPrintController;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Services\Facebook\Pixel;
use App\Services\Payments\Drivers\Flitt;
use App\Services\Payments\PaymentCheck;
use App\Services\Sms\SmsOffice;
use App\Support\Cart;
use App\Support\Catalog;
use App\Support\Site;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected string $view = 'filament.orders.view-order';

    public array $lines = [];

    public string $productSearch = '';

    public string $status = '';

    public array $customer = [];

    public array $delivery = [];

    public array $payment = [];

    public string $paymentStatus = '';

    public string $shippingFee = '0';

    public string $comment = '';

    public bool $linesChanged = false;

    public const LINK_DAYS = [1, 3, 7, 14, 30];

    public string $linkMethod = '';

    public int $linkDays = 7;

    public ?string $generatedLink = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->order()->logFirstOpen();
        $this->fillFromOrder();

        $current = $this->order()->payment_method;
        $this->linkMethod = $this->linkMethods->contains('code', $current) ? $current : (string) $this->linkMethods->first()?->code;
    }

    public function getTitle(): string
    {
        return __('admin.orders.title', ['number' => $this->order()->number]);
    }

    public function getSubheading(): ?string
    {
        return $this->order()->created_at?->format('d.m.Y H:i');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label(__('admin.print.button'))
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (): string => OrderPrintController::url([$this->order()->id]), shouldOpenInNewTab: true),
            Action::make('invoice')
                ->label(__('admin.print.invoice'))
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('gray')
                ->url(fn (): string => InvoiceController::adminUrl([$this->order()->id]), shouldOpenInNewTab: true),
            Action::make('call')
                ->label(fn (): string => $this->order()->phone)
                ->icon(Heroicon::OutlinedPhone)
                ->color('gray')
                ->url(fn (): string => 'tel:'.preg_replace('/[^\d+]/', '', $this->order()->phone)),
            Action::make('refund')
                ->label(__('payments.refund.button'))
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('danger')
                ->visible(fn (): bool => ! Operators::active() && $this->refundableAmount() > 0)
                ->modalHeading(fn (): string => __('payments.refund.heading', ['number' => $this->order()->number]))
                ->modalDescription(fn (): string => __('payments.refund.help', ['amount' => Catalog::money($this->refundableAmount())]))
                ->modalIcon(Heroicon::OutlinedArrowUturnLeft)
                ->modalSubmitActionLabel(__('payments.refund.submit'))
                ->fillForm(fn (): array => ['amount' => $this->refundableAmount()])
                ->schema([
                    TextInput::make('amount')
                        ->label(__('payments.refund.amount'))
                        ->numeric()
                        ->minValue(0.01)
                        ->maxValue(fn (): float => $this->refundableAmount())
                        ->suffix('₾')
                        ->required(),
                    Textarea::make('comment')
                        ->label(__('payments.refund.comment'))
                        ->rows(2)
                        ->maxLength(1000),
                ])
                ->action(fn (array $data) => $this->refund((float) $data['amount'], $data['comment'] ?? null)),
            Action::make('save')
                ->label(__('admin.orders.save'))
                ->icon(Heroicon::OutlinedCheck)
                ->visible(fn (): bool => $this->order()->isEditable())
                ->action(fn () => $this->save()),
            DeleteAction::make()
                ->label(__('admin.orders.delete'))
                ->modalHeading(fn (): string => __('admin.orders.delete_heading', ['number' => $this->order()->number]))
                ->modalDescription(__('admin.orders.delete_help'))
                ->successRedirectUrl(OrderResource::getUrl()),
            RestoreAction::make()
                ->label(__('admin.orders.restore')),
            ForceDeleteAction::make()
                ->label(__('admin.orders.force_delete'))
                ->modalHeading(fn (): string => __('admin.orders.force_delete_heading', ['number' => $this->order()->number]))
                ->modalDescription(__('admin.orders.force_delete_help'))
                ->successRedirectUrl(OrderResource::getUrl()),
        ];
    }

    public function order(): Order
    {
        return $this->getRecord();
    }

    public function fillFromOrder(): void
    {
        $order = $this->order()->refresh()->load('items');

        $this->status = $order->status;
        $this->customer = [
            'first_name' => (string) $order->first_name,
            'last_name' => (string) $order->last_name,
            'phone' => (string) $order->phone,
            'email' => (string) $order->email,
        ];
        $this->delivery = [
            'shipping_method' => (string) $order->shipping_method,
            'city' => (string) $order->city,
            'address' => (string) $order->address,
            'apartment' => (string) $order->apartment,
            'note' => (string) $order->note,
        ];
        $this->payment = [
            'payment_method' => (string) $order->payment_method,
        ];
        $this->paymentStatus = (string) $order->payment_status;
        $this->shippingFee = number_format((float) $order->shipping, 2, '.', '');
        $this->lines = $order->items
            ->mapWithKeys(fn (OrderItem $item) => ['i'.$item->id => [
                'item_id' => $item->id,
                'product_id' => $item->product_id,
                'name' => $item->name,
                'options' => $item->options,
                'price' => (float) $item->unit_price,
                'quantity' => $item->quantity,
            ]])
            ->all();
        $this->linesChanged = false;

        unset($this->totals, $this->lineProducts, $this->events, $this->transactions);
    }

    #[Computed]
    public function totals(): array
    {
        return $this->order()->totalsFor($this->lines, (float) (is_numeric($this->shippingFee) ? $this->shippingFee : 0));
    }

    #[Computed]
    public function lineProducts(): Collection
    {
        return Product::query()
            ->with(['kaTranslation', 'coverImage'])
            ->whereKey(collect($this->lines)->pluck('product_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');
    }

    #[Computed]
    public function foundProducts(): Collection
    {
        $search = trim($this->productSearch);

        if (mb_strlen($search) < 2 || ! $this->order()->isEditable()) {
            return collect();
        }

        return Product::query()
            ->with(['kaTranslation', 'coverImage'])
            ->where(fn (Builder $query) => $query
                ->where('sku', 'like', "%{$search}%")
                ->orWhere('supplier_sku', 'like', "%{$search}%")
                ->orWhereHas('translations', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")))
            ->orderByDesc('is_active')
            ->orderByDesc('stock')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function events(): Collection
    {
        return $this->order()->events()->with('user')->get();
    }

    #[Computed]
    public function transactions(): Collection
    {
        return $this->order()->transactions()->latest('id')->get();
    }

    #[Computed]
    public function paymentMethods(): Collection
    {
        return PaymentMethod::query()->withTranslation()->orderBy('sort_order')->get();
    }

    public function changePaymentStatus(): void
    {
        $this->validate(['paymentStatus' => ['required', Rule::in(Order::PAYMENT_STATUSES)]]);

        if ($this->order()->setPaymentStatus($this->paymentStatus, __('admin.orders.payment_set_by_hand'))) {
            Notification::make()->title(__('admin.orders.payment_status_changed'))->success()->send();

            if ($this->paymentStatus === Order::PAYMENT_PAID) {
                $this->reportSale();
            }
        }

        $this->fillFromOrder();
    }

    public function askBank(): void
    {
        $answer = app(PaymentCheck::class)->ask($this->order());

        Notification::make()->title($answer['message'])->{$answer['paid'] ? 'success' : 'warning'}()->send();

        $this->fillFromOrder();
    }

    public function refundTransaction(): ?PaymentTransaction
    {
        return $this->order()->transactions()
            ->where('driver', Flitt::NAME)
            ->where('status', PaymentTransaction::SUCCESS)
            ->latest('id')
            ->first();
    }

    public function refundableAmount(): float
    {
        $transaction = $this->refundTransaction();

        return $transaction ? app(Flitt::class)->refundable($transaction) : 0.0;
    }

    protected function refund(float $amount, ?string $comment = null): void
    {
        abort_if(Operators::active(), 403);

        $transaction = $this->refundTransaction();

        if (! $transaction) {
            return;
        }

        $flitt = app(Flitt::class);
        $result = $flitt->refund($transaction, $amount, $comment, auth()->user()?->email);
        $order = $this->order();
        $money = Catalog::money($amount);

        if ($result['status'] === 'approved') {
            $order->log('payment', trim(__('payments.refund.logged', ['amount' => $money]).' '.($comment ? '— '.$comment : '')));

            if ($flitt->refundable($transaction->fresh()) <= 0) {
                $order->setPaymentStatus(Order::PAYMENT_REFUNDED, __('payments.refund.full'));
            }

            Notification::make()->title(__('payments.refund.done', ['amount' => $money]))->success()->send();
        } elseif (in_array($result['status'], ['created', 'processing'], true)) {
            $order->log('payment', __('payments.refund.pending_log', ['amount' => $money]));
            Notification::make()->title(__('payments.refund.pending'))->warning()->send();
        } else {
            Notification::make()
                ->title(__('payments.refund.failed'))
                ->body($result['message'])
                ->danger()
                ->persistent()
                ->send();
        }

        unset($this->transactions, $this->events);
    }

    #[Computed]
    public function linkMethods(): Collection
    {
        $total = (float) $this->order()->total;

        return PaymentMethod::query()
            ->active()
            ->withTranslation()
            ->get()
            ->filter(fn (PaymentMethod $method) => $method->isPayableOnline() && $method->fitsTotal($total))
            ->values();
    }

    public function canSendLinkSms(): bool
    {
        return app(SmsOffice::class)->isConfigured() && SmsOffice::normalize($this->order()->phone) !== null;
    }

    public function generateLink(): void
    {
        $this->validate([
            'linkMethod' => ['required', Rule::in($this->linkMethods->pluck('code')->all())],
            'linkDays' => ['required', 'integer', Rule::in(self::LINK_DAYS)],
        ]);

        $order = $this->order();
        $this->generatedLink = $order->payLink($this->linkDays, $this->linkMethod);

        $order->log('payment', __('admin.orders.pay_link_created', [
            'method' => PaymentMethod::labelFor($this->linkMethod),
            'days' => $this->linkDays,
        ]));

        unset($this->events);
    }

    public function linkMessage(): string
    {
        $order = $this->order();

        return __('admin.orders.pay_link_message', [
            'site' => Site::name(),
            'number' => $order->number,
            'amount' => Catalog::money($order->total),
            'method' => PaymentMethod::labelFor($this->linkMethod),
            'url' => (string) $this->generatedLink,
        ]);
    }

    public function sendLinkSms(): void
    {
        if ($this->generatedLink === null) {
            return;
        }

        $order = $this->order();

        if (! app(SmsOffice::class)->send($order->phone, $this->linkMessage(), $order->number)) {
            Notification::make()->title(__('admin.orders.pay_link_sms_failed'))->danger()->send();

            return;
        }

        $order->log('payment', __('admin.orders.pay_link_sms_sent', ['phone' => $order->phone]));
        Notification::make()->title(__('admin.orders.pay_link_sms_sent', ['phone' => $order->phone]))->success()->send();

        unset($this->events);
    }

    public function priceOf(Product $product): float
    {
        return (float) (Catalog::find($product->id)['price'] ?? $product->price);
    }

    public function imageOf(?Product $product): ?string
    {
        $path = $product?->coverImage?->path;

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public function siteUrlOf(?Product $product): ?string
    {
        return $product && $product->is_active ? ProductsTable::siteUrl($product) : null;
    }

    public function add(int $productId): void
    {
        if (! $this->order()->isEditable()) {
            return;
        }

        $product = Product::query()->with('kaTranslation')->find($productId);

        if (! $product) {
            return;
        }

        $existing = collect($this->lines)->search(fn (array $line) => $line['product_id'] === $product->id && blank($line['options']));

        if ($existing !== false) {
            $this->lines[$existing]['quantity']++;
        } else {
            $this->lines['p'.$product->id.'-'.count($this->lines)] = [
                'item_id' => null,
                'product_id' => $product->id,
                'name' => $product->kaTranslation?->name ?? $product->sku,
                'options' => null,
                'price' => $this->priceOf($product),
                'quantity' => 1,
            ];
        }

        $this->productSearch = '';
        $this->linesChanged = true;
    }

    public function increment(string $key): void
    {
        if ($this->order()->isEditable() && isset($this->lines[$key])) {
            $this->lines[$key]['quantity'] = min(999, (int) $this->lines[$key]['quantity'] + 1);
            $this->linesChanged = true;
        }
    }

    public function decrement(string $key): void
    {
        if ($this->order()->isEditable() && isset($this->lines[$key]) && $this->lines[$key]['quantity'] > 1) {
            $this->lines[$key]['quantity']--;
            $this->linesChanged = true;
        }
    }

    public function remove(string $key): void
    {
        if ($this->order()->isEditable()) {
            unset($this->lines[$key]);
            $this->linesChanged = true;
        }
    }

    public function recalculateShipping(): void
    {
        $this->shippingFee = number_format(Cart::shippingFor($this->totals['subtotal']), 2, '.', '');
    }

    protected function reportSale(): void
    {
        $order = $this->order();

        if (app(Pixel::class)->reportSale($order)) {
            $order->log('note', __('admin.orders.purchase_reported'));
            unset($this->events);
        }
    }

    public function changeStatus(): void
    {
        $this->validate(['status' => ['required', Rule::in(Order::STATUSES)]]);

        $order = $this->order();
        $from = $order->status;

        if ($from === $this->status) {
            return;
        }

        $order->update(['status' => $this->status]);
        $order->log('status', from: $from, to: $this->status);

        if ($this->status === Pixel::COMPLETED) {
            $this->reportSale();
        }

        unset($this->events);

        Notification::make()->title(__('admin.orders.status_changed'))->success()->send();
    }

    public function addNote(): void
    {
        $this->validate(['comment' => ['required', 'string', 'max:2000']], [], ['comment' => __('admin.orders.history_note')]);

        $this->order()->log('note', trim($this->comment));
        $this->comment = '';

        unset($this->events);
    }

    public function save(): void
    {
        $order = $this->order();

        if (! $order->isEditable()) {
            Notification::make()->title(__('admin.orders.closed'))->warning()->send();

            return;
        }

        $this->validate($this->editRules(), [], $this->editAttributes());

        $totals = $this->totals;
        $changed = [];

        DB::transaction(function () use ($order, $totals, &$changed): void {
            $order->fill([
                'first_name' => trim($this->customer['first_name']),
                'last_name' => trim($this->customer['last_name']) ?: null,
                'phone' => trim($this->customer['phone']),
                'email' => mb_strtolower(trim($this->customer['email'])) ?: null,
                'shipping_method' => $this->delivery['shipping_method'] ?: null,
                'city' => $this->delivery['city'] ?: null,
                'address' => trim($this->delivery['address']) ?: null,
                'apartment' => trim($this->delivery['apartment']) ?: null,
                'note' => trim($this->delivery['note']) ?: null,
                'payment_method' => $this->payment['payment_method'],
                ...$totals,
            ]);

            $sections = [
                'customer' => ['first_name', 'last_name', 'phone', 'email'],
                'delivery' => ['shipping_method', 'city', 'address', 'apartment', 'note'],
                'payment' => ['payment_method'],
                'shipping' => ['shipping'],
            ];

            foreach ($sections as $section => $fields) {
                if ($order->isDirty($fields)) {
                    $changed[] = __("admin.orders.changed.$section");
                }
            }

            if ($this->linesChanged) {
                $changed[] = __('admin.orders.changed.items');
                $this->syncItems($order);
            }

            $order->save();
        });

        if ($changed === []) {
            Notification::make()->title(__('admin.orders.nothing_changed'))->info()->send();

            return;
        }

        $order->log('edit', __('admin.orders.changed_note', ['what' => implode(', ', $changed)]));
        app(Pixel::class)->refreshAfterEdit($order);

        $this->fillFromOrder();

        Notification::make()->title(__('admin.orders.saved'))->success()->send();
    }

    protected function syncItems(Order $order): void
    {
        $kept = collect($this->lines)->pluck('item_id')->filter()->all();

        $order->items()->whereNotIn('id', $kept)->delete();

        foreach ($this->lines as $line) {
            $values = [
                'unit_price' => $line['price'],
                'quantity' => (int) $line['quantity'],
                'total' => round((float) $line['price'] * (int) $line['quantity'], 2),
            ];

            if ($line['item_id']) {
                $order->items()->whereKey($line['item_id'])->update($values);
            } else {
                $order->items()->create([
                    'product_id' => $line['product_id'],
                    'name' => $line['name'],
                    'options' => $line['options'],
                    ...$values,
                ]);
            }
        }
    }

    protected function editRules(): array
    {
        return [
            'customer.first_name' => ['required', 'string', 'max:60'],
            'customer.last_name' => ['nullable', 'string', 'max:60'],
            'customer.phone' => ['required', 'string', 'max:30'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            'delivery.shipping_method' => ['nullable', Rule::in(Order::SHIPPING_METHODS)],
            'delivery.city' => ['nullable', Rule::in(Order::CITIES)],
            'delivery.address' => ['nullable', 'string', 'max:255'],
            'delivery.apartment' => ['nullable', 'string', 'max:255'],
            'delivery.note' => ['nullable', 'string', 'max:500'],
            'payment.payment_method' => ['required', Rule::exists('payment_methods', 'code')],
            'shippingFee' => ['required', 'numeric', 'min:0', 'max:100000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    protected function editAttributes(): array
    {
        return [
            'customer.first_name' => __('admin.orders.first_name'),
            'customer.last_name' => __('admin.orders.last_name'),
            'customer.phone' => __('admin.orders.phone'),
            'customer.email' => __('admin.orders.email'),
            'delivery.shipping_method' => __('admin.orders.shipping_method'),
            'delivery.city' => __('admin.orders.city'),
            'delivery.address' => __('admin.orders.address'),
            'delivery.apartment' => __('admin.orders.apartment'),
            'delivery.note' => __('admin.orders.note'),
            'payment.payment_method' => __('admin.orders.payment_method'),
            'shippingFee' => __('admin.orders.shipping'),
            'lines' => __('admin.orders.items'),
            'lines.*.quantity' => __('admin.orders.quantity'),
        ];
    }
}
