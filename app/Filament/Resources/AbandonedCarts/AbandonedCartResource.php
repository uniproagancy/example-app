<?php

namespace App\Filament\Resources\AbandonedCarts;

use App\Filament\Resources\AbandonedCarts\Pages\ManageAbandonedCarts;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\HasAdminLabels;
use App\Models\AbandonedCart;
use App\Services\Sms\SmsOffice;
use App\Support\Catalog;
use App\Support\Site;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AbandonedCartResource extends Resource
{
    use HasAdminLabels;

    protected static string $labelKey = 'abandoned_carts';

    protected static string $groupKey = 'sales';

    protected static ?string $model = AbandonedCart::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = AbandonedCart::query()->abandoned()->whereNull('contacted_at')->where('updated_at', '>=', now()->subDays(7))->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function status(AbandonedCart $cart): string
    {
        return match (true) {
            $cart->order_id !== null => 'recovered',
            $cart->contacted_at !== null => 'contacted',
            $cart->updated_at > now()->subMinutes(AbandonedCart::IDLE_MINUTES) => 'active',
            default => 'abandoned',
        };
    }

    public static function statusOptions(): array
    {
        return collect(['abandoned', 'contacted', 'recovered', 'active'])
            ->mapWithKeys(fn (string $status) => [$status => __("admin.abandoned_carts.statuses.$status")])
            ->all();
    }

    public static function reminderText(AbandonedCart $cart): string
    {
        return __('admin.abandoned_carts.sms_text', [
            'site' => Site::name(),
            'amount' => Catalog::money($cart->subtotal),
            'url' => $cart->restoreUrl(),
        ], $cart->locale);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('order:id,number'))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('status')
                    ->label(__('admin.common.status'))
                    ->state(fn (AbandonedCart $record) => static::status($record))
                    ->formatStateUsing(fn (string $state) => static::statusOptions()[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'recovered' => 'success',
                        'contacted' => 'info',
                        'active' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('name')
                    ->label(__('admin.abandoned_carts.customer'))
                    ->placeholder(__('admin.abandoned_carts.no_name'))
                    ->description(fn (AbandonedCart $record) => collect([$record->phone, $record->email])->filter()->implode(' · '))
                    ->searchable(['name', 'phone', 'email']),
                TextColumn::make('lines')
                    ->label(__('admin.abandoned_carts.products'))
                    ->state(fn (AbandonedCart $record) => collect($record->lines)->map(fn (array $line) => $line['quantity'].' × '.$line['name'])->implode(', '))
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('subtotal')
                    ->label(__('admin.abandoned_carts.subtotal'))
                    ->formatStateUsing(fn ($state) => Catalog::money($state))
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__('admin.abandoned_carts.left_at'))
                    ->dateTime('d.m.Y H:i')
                    ->description(fn (AbandonedCart $record) => $record->updated_at->diffForHumans())
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('state')
                    ->label(__('admin.common.status'))
                    ->options([
                        'open' => __('admin.abandoned_carts.filters.open'),
                        'recovered' => __('admin.abandoned_carts.filters.recovered'),
                    ])
                    ->default('open')
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'open' => $query->abandoned(),
                        'recovered' => $query->whereNotNull('order_id'),
                        default => $query,
                    }),
            ])
            ->recordActions([
                Action::make('call')
                    ->hiddenLabel()
                    ->tooltip(__('admin.abandoned_carts.call'))
                    ->icon(Heroicon::OutlinedPhone)
                    ->color('gray')
                    ->visible(fn (AbandonedCart $record) => filled($record->phone))
                    ->url(fn (AbandonedCart $record) => 'tel:'.preg_replace('/[^\d+]/', '', (string) $record->phone)),
                Action::make('remind')
                    ->label(__('admin.abandoned_carts.remind'))
                    ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                    ->color('primary')
                    ->visible(fn (AbandonedCart $record) => $record->order_id === null)
                    ->modalHeading(__('admin.abandoned_carts.remind'))
                    ->modalDescription(__('admin.abandoned_carts.remind_help'))
                    ->fillForm(fn (AbandonedCart $record) => [
                        'link' => $record->restoreUrl(),
                        'text' => static::reminderText($record),
                    ])
                    ->schema([
                        TextInput::make('link')
                            ->label(__('admin.abandoned_carts.link'))
                            ->readOnly()
                            ->copyable(__('admin.orders.copied')),
                        TextInput::make('text')
                            ->label(__('admin.abandoned_carts.sms'))
                            ->readOnly()
                            ->copyable(__('admin.orders.copied')),
                    ])
                    ->modalSubmitActionLabel(__('admin.orders.pay_link_sms'))
                    ->action(function (AbandonedCart $record, Action $action): void {
                        $sent = app(SmsOffice::class)->send($record->phone, static::reminderText($record), 'cart-'.$record->id);

                        if (! $sent) {
                            Notification::make()->title(__('admin.orders.pay_link_sms_failed'))->danger()->send();
                            $action->halt();
                        }

                        $record->update(['reminded_at' => now(), 'contacted_at' => $record->contacted_at ?? now()]);
                        Notification::make()->title(__('admin.orders.pay_link_sms_sent', ['phone' => $record->phone]))->success()->send();
                    }),
                Action::make('contacted')
                    ->hiddenLabel()
                    ->tooltip(fn (AbandonedCart $record) => $record->contacted_at ? __('admin.abandoned_carts.mark_open') : __('admin.abandoned_carts.mark_contacted'))
                    ->icon(fn (AbandonedCart $record) => $record->contacted_at ? Heroicon::OutlinedArrowUturnLeft : Heroicon::OutlinedCheckCircle)
                    ->color(fn (AbandonedCart $record) => $record->contacted_at ? 'gray' : 'success')
                    ->visible(fn (AbandonedCart $record) => $record->order_id === null)
                    ->action(fn (AbandonedCart $record) => $record->update(['contacted_at' => $record->contacted_at ? null : now()])),
                Action::make('order')
                    ->label(fn (AbandonedCart $record) => '#'.$record->order?->number)
                    ->icon(Heroicon::OutlinedShoppingBag)
                    ->color('success')
                    ->visible(fn (AbandonedCart $record) => $record->order !== null)
                    ->url(fn (AbandonedCart $record) => OrderResource::getUrl('view', ['record' => $record->order_id])),
                DeleteAction::make()->hiddenLabel(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAbandonedCarts::route('/'),
        ];
    }
}
