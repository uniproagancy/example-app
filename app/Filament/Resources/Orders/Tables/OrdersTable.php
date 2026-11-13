<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\Orders\OrdersExport;
use App\Support\Catalog;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordClasses(fn (Order $record): ?string => $record->trashed() ? 'opacity-60' : null)
            ->columns([
                TextColumn::make('number')
                    ->label(__('admin.orders.number'))
                    ->weight(FontWeight::Bold)
                    ->description(fn (Order $record): ?string => $record->trashed() ? __('admin.orders.deleted_at', ['date' => $record->deleted_at->format('d.m.Y H:i')]) : null)
                    ->searchable(),
                TextColumn::make('first_name')
                    ->label(__('admin.orders.customer'))
                    ->formatStateUsing(fn (Order $record) => trim($record->first_name.' '.$record->last_name))
                    ->description(fn (Order $record) => $record->phone)
                    ->searchable(['first_name', 'last_name', 'phone', 'email']),
                TextColumn::make('status')
                    ->label(__('admin.common.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => OrderResource::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state) => OrderResource::statusColor($state)),
                TextColumn::make('payment_status')
                    ->label(__('admin.orders.payment_status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => OrderResource::paymentStatusOptions()[$state] ?? $state)
                    ->color(fn (string $state) => OrderResource::paymentStatusColor($state))
                    ->description(fn (Order $record) => OrderResource::paymentLabel($record->payment_method)),
                TextColumn::make('total')
                    ->label(__('admin.orders.total'))
                    ->formatStateUsing(fn ($state) => Catalog::money($state))
                    ->weight(FontWeight::Bold)
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.common.status'))
                    ->options(fn (): array => collect(OrderResource::statusOptions())
                        ->map(fn (string $label, string $status): string => ($count = Order::query()->where('status', $status)->count()) ? "$label ($count)" : $label)
                        ->all()),
                SelectFilter::make('source')
                    ->label(__('admin.orders.source'))
                    ->options(OrderResource::sourceOptions()),
                SelectFilter::make('payment_status')
                    ->label(__('admin.orders.payment_status'))
                    ->options(OrderResource::paymentStatusOptions()),
                SelectFilter::make('payment_method')
                    ->label(__('admin.orders.payment_method'))
                    ->options(fn (): array => PaymentMethod::query()->orderBy('sort_order')->get()->mapWithKeys(fn (PaymentMethod $method) => [$method->code => $method->name ?? $method->code])->all()),
                SelectFilter::make('city')
                    ->label(__('admin.orders.city'))
                    ->options(collect(Order::CITIES)->mapWithKeys(fn (string $city) => [$city => __("checkout.cities.$city")])->all()),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label(__('admin.common.starts_at')),
                        DatePicker::make('until')->label(__('admin.common.ends_at')),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))),
                Filter::make('with_deleted')
                    ->label(__('admin.orders.with_deleted'))
                    ->checkbox()
                    ->query(fn (Builder $query): Builder => $query->withoutGlobalScopes([SoftDeletingScope::class])),
            ])
            ->recordActions([
                ViewAction::make(),
                OrderResource::changeStatusAction()
                    ->hiddenLabel()
                    ->tooltip(__('admin.orders.change_status')),
                DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip(__('admin.orders.delete'))
                    ->modalHeading(fn (Order $record): string => __('admin.orders.delete_heading', ['number' => $record->number]))
                    ->modalDescription(__('admin.orders.delete_help')),
                RestoreAction::make()
                    ->hiddenLabel()
                    ->tooltip(__('admin.orders.restore')),
                ForceDeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip(__('admin.orders.force_delete'))
                    ->modalHeading(fn (Order $record): string => __('admin.orders.force_delete_heading', ['number' => $record->number]))
                    ->modalDescription(__('admin.orders.force_delete_help')),
            ])
            ->headerActions([
                Action::make('export')
                    ->label(__('admin.orders.export.label'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->action(fn (HasTable $livewire) => app(OrdersExport::class)->download($livewire->getFilteredTableQuery())),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('export')
                        ->label(__('admin.orders.export.selected'))
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => app(OrdersExport::class)->download(Order::withTrashed()->whereKey($records->modelKeys()))),
                    DeleteBulkAction::make()
                        ->modalDescription(__('admin.orders.delete_help')),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make()
                        ->modalDescription(__('admin.orders.force_delete_help')),
                ]),
            ]);
    }
}
