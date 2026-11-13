<?php

namespace App\Filament\Resources\ContactMessages;

use App\Filament\Resources\ContactMessages\Pages\ManageContactMessages;
use App\Filament\Support\AdminOnly;
use App\Filament\Support\HasAdminLabels;
use App\Models\ContactMessage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContactMessageResource extends Resource
{
    use AdminOnly;
    use HasAdminLabels;

    protected static string $labelKey = 'messages';

    protected static string $groupKey = 'sales';

    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = ContactMessage::query()->whereNull('handled_at')->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function topicOptions(): array
    {
        return collect(ContactMessage::TOPICS)->mapWithKeys(fn (string $topic) => [$topic => __("pages.contact.topics.$topic")])->all();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextEntry::make('name')->label(__('admin.messages.name')),
                TextEntry::make('phone')
                    ->label(__('admin.messages.phone'))
                    ->url(fn (ContactMessage $record) => 'tel:'.preg_replace('/\s+/', '', $record->phone))
                    ->copyable(),
                TextEntry::make('topic')
                    ->label(__('admin.messages.topic'))
                    ->formatStateUsing(fn (string $state) => static::topicOptions()[$state] ?? $state)
                    ->badge(),
                TextEntry::make('order_number')
                    ->label(__('admin.messages.order_number'))
                    ->placeholder(__('admin.common.none')),
                TextEntry::make('message')
                    ->label(__('admin.messages.message'))
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime('d.m.Y H:i'),
                TextEntry::make('handled_at')
                    ->label(__('admin.messages.handled'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder(__('admin.messages.open')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                IconColumn::make('handled_at')
                    ->label('')
                    ->state(fn (ContactMessage $record) => $record->handled_at !== null)
                    ->boolean(),
                TextColumn::make('name')
                    ->label(__('admin.messages.name'))
                    ->description(fn (ContactMessage $record) => $record->phone)
                    ->searchable(['name', 'phone', 'order_number']),
                TextColumn::make('topic')
                    ->label(__('admin.messages.topic'))
                    ->formatStateUsing(fn (string $state) => static::topicOptions()[$state] ?? $state)
                    ->badge(),
                TextColumn::make('message')
                    ->label(__('admin.messages.message'))
                    ->limit(70)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('handled')
                    ->label(__('admin.messages.handled'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('handled_at'),
                        false: fn (Builder $query) => $query->whereNull('handled_at'),
                    ),
                SelectFilter::make('topic')
                    ->label(__('admin.messages.topic'))
                    ->options(static::topicOptions()),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('toggleHandled')
                    ->hiddenLabel()
                    ->tooltip(fn (ContactMessage $record) => $record->handled_at ? __('admin.messages.mark_open') : __('admin.messages.mark_handled'))
                    ->icon(fn (ContactMessage $record) => $record->handled_at ? Heroicon::OutlinedArrowUturnLeft : Heroicon::OutlinedCheckCircle)
                    ->color(fn (ContactMessage $record) => $record->handled_at ? 'gray' : 'success')
                    ->action(fn (ContactMessage $record) => $record->update(['handled_at' => $record->handled_at ? null : now()])),
                DeleteAction::make()->hiddenLabel(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageContactMessages::route('/'),
        ];
    }
}
