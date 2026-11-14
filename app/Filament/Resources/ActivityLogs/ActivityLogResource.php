<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Support\AdminOnly;
use App\Filament\Support\HasAdminLabels;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

class ActivityLogResource extends Resource
{
    use AdminOnly;
    use HasAdminLabels;

    protected static string $labelKey = 'activity';

    protected static string $groupKey = 'system';

    protected static ?string $model = ActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 30;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function typeLabel(string $type): string
    {
        $key = 'admin.activity.types.'.Str::snake(class_basename($type));

        return __($key) !== $key ? __($key) : class_basename($type);
    }

    public static function typeOptions(): array
    {
        return ActivityLog::query()->distinct()->pluck('subject_type')
            ->mapWithKeys(fn (string $type) => [$type => static::typeLabel($type)])
            ->sort()
            ->all();
    }

    public static function subjectUrl(ActivityLog $log): ?string
    {
        if ($log->event === 'deleted') {
            return null;
        }

        try {
            $subject = $log->subject;

            return match (true) {
                $subject instanceof Product => ProductResource::getUrl('edit', ['record' => $subject]),
                $subject instanceof ProductTranslation && $subject->product_id => ProductResource::getUrl('edit', ['record' => $subject->product_id]),
                $subject instanceof Order => OrderResource::getUrl('view', ['record' => $subject]),
                $subject instanceof Category => CategoryResource::getUrl('edit', ['record' => $subject]),
                $subject instanceof CategoryTranslation && $subject->category_id => CategoryResource::getUrl('edit', ['record' => $subject->category_id]),
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    public static function formatValue(mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' => '—',
            is_bool($value) => $value ? __('admin.activity.yes') : __('admin.activity.no'),
            is_array($value) => Str::limit(json_encode($value, JSON_UNESCAPED_UNICODE), 120),
            default => Str::limit((string) $value, 120),
        };
    }

    public static function changeLines(ActivityLog $log, ?int $limit = null): array
    {
        $changes = collect($log->changes ?? []);

        $lines = $changes->map(function (mixed $change, string $field) use ($log) {
            if ($log->event === 'updated' && is_array($change) && array_key_exists('new', $change)) {
                return $field.': '.static::formatValue($change['old'] ?? null).' → '.static::formatValue($change['new']);
            }

            return $field.': '.static::formatValue($change);
        })->values();

        if ($limit !== null && $lines->count() > $limit) {
            return [...$lines->take($limit)->all(), __('admin.activity.more', ['count' => $lines->count() - $limit])];
        }

        return $lines->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('admin.activity.when'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('admin.activity.user'))
                    ->placeholder(__('admin.common.none'))
                    ->searchable(),
                TextColumn::make('event')
                    ->label(__('admin.activity.event'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("admin.activity.events.$state"))
                    ->color(fn (string $state) => match ($state) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        default => 'info',
                    }),
                TextColumn::make('subject_type')
                    ->label(__('admin.activity.type'))
                    ->formatStateUsing(fn (string $state) => static::typeLabel($state)),
                TextColumn::make('subject_label')
                    ->label(__('admin.activity.subject'))
                    ->searchable()
                    ->url(fn (ActivityLog $record) => static::subjectUrl($record))
                    ->wrap(),
                TextColumn::make('changes')
                    ->label(__('admin.activity.changes'))
                    ->state(fn (ActivityLog $record) => static::changeLines($record, 3))
                    ->listWithLineBreaks()
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label(__('admin.activity.user'))
                    ->options(fn () => User::query()
                        ->where(fn (Builder $query) => $query->where('is_admin', true)->orWhere('is_operator', true))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
                SelectFilter::make('subject_type')
                    ->label(__('admin.activity.type'))
                    ->options(fn () => static::typeOptions()),
                SelectFilter::make('event')
                    ->label(__('admin.activity.event'))
                    ->options(collect(['created', 'updated', 'deleted'])->mapWithKeys(fn (string $event) => [$event => __("admin.activity.events.$event")])->all()),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label(__('admin.common.starts_at')),
                        DatePicker::make('until')->label(__('admin.common.ends_at')),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                Action::make('details')
                    ->label(__('admin.activity.details'))
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading(fn (ActivityLog $record) => static::typeLabel($record->subject_type).' — '.$record->subject_label)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('admin.activity.close'))
                    ->modalContent(fn (ActivityLog $record) => view('filament.activity.details', [
                        'log' => $record,
                        'lines' => static::changeLines($record),
                    ]))
                    ->visible(fn (ActivityLog $record) => filled($record->changes)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
        ];
    }

    public static function forSubject(Model $model): Builder
    {
        return ActivityLog::query()->where('subject_type', $model->getMorphClass())->where('subject_id', $model->getKey());
    }
}
