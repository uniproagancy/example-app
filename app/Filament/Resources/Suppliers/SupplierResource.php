<?php

namespace App\Filament\Resources\Suppliers;

use App\Filament\Pages\ImportSpreadsheet;
use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Resources\Suppliers\RelationManagers\OffersRelationManager;
use App\Filament\Support\HasAdminLabels;
use App\Models\Supplier;
use App\Services\Import\HasStockFeed;
use App\Services\Import\SupplierActions;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Throwable;

class SupplierResource extends Resource
{
    use HasAdminLabels;

    public const COMMON_CONFIG = ['require_stock', 'require_image', 'publish_on_import', 'min_cost', 'rate', 'columns'];

    protected static string $labelKey = 'suppliers';

    protected static string $groupKey = 'import';

    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make([
                    Section::make(__('admin.common.settings'))
                        ->columns(2)
                        ->schema([
                            TextInput::make('name')
                                ->label(__('admin.suppliers.name'))
                                ->required()
                                ->maxLength(255),
                            TextInput::make('code')
                                ->label(__('admin.suppliers.code'))
                                ->required()
                                ->alphaDash()
                                ->maxLength(40)
                                ->unique(ignoreRecord: true)
                                ->disabledOn('edit'),
                            Select::make('driver')
                                ->label(__('admin.suppliers.driver'))
                                ->options(SupplierActions::drivers())
                                ->required(),
                            TextInput::make('priority')
                                ->label(__('admin.suppliers.priority'))
                                ->helperText(__('admin.suppliers.priority_help'))
                                ->integer()
                                ->minValue(0)
                                ->default(100),
                        ]),

                    Section::make(__('admin.suppliers.markup'))
                        ->description(__('admin.suppliers.markup_help'))
                        ->schema([
                            Repeater::make('markup')
                                ->hiddenLabel()
                                ->columns(3)
                                ->defaultItems(0)
                                ->reorderableWithDragAndDrop()
                                ->addActionLabel(__('admin.suppliers.markup_tier'))
                                ->schema([
                                    TextInput::make('up_to')->label(__('admin.suppliers.up_to'))->numeric()->minValue(0)->suffix('₾'),
                                    TextInput::make('percent')->label(__('admin.suppliers.percent'))->numeric()->suffix('%'),
                                    TextInput::make('add')->label(__('admin.suppliers.add'))->numeric()->suffix('₾'),
                                ]),
                        ]),

                    Section::make(__('admin.suppliers.columns'))
                        ->description(__('admin.suppliers.columns_help'))
                        ->icon(Heroicon::OutlinedTableCells)
                        ->collapsible()
                        ->schema([
                            KeyValue::make('config.columns')
                                ->hiddenLabel()
                                ->keyLabel(__('admin.suppliers.column_role'))
                                ->valueLabel(__('admin.suppliers.column_letter'))
                                ->keyPlaceholder('key')
                                ->valuePlaceholder('A')
                                ->addActionLabel(__('admin.suppliers.column_add'))
                                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    $columns = static::normalizeColumns((array) $value);

                                    if ($columns !== [] && blank($columns['key'] ?? null)) {
                                        $fail(__('admin.suppliers.column_key_required'));
                                    }
                                }),
                        ]),

                    Section::make(__('admin.suppliers.advanced'))
                        ->description(__('admin.suppliers.advanced_help'))
                        ->collapsible()
                        ->collapsed()
                        ->schema([
                            CodeEditor::make('config_json')
                                ->hiddenLabel()
                                ->language(Language::Json)
                                ->rule('json'),
                        ]),
                ])->columnSpan(['lg' => 2]),

                Group::make([
                    Section::make(__('admin.suppliers.rules'))
                        ->schema([
                            Toggle::make('is_active')
                                ->label(__('admin.common.is_active'))
                                ->default(false),
                            Toggle::make('config.require_stock')
                                ->label(__('admin.suppliers.require_stock'))
                                ->helperText(__('admin.suppliers.require_stock_help'))
                                ->default(true),
                            Toggle::make('config.require_image')
                                ->label(__('admin.suppliers.require_image'))
                                ->helperText(__('admin.suppliers.require_image_help')),
                            Toggle::make('config.publish_on_import')
                                ->label(__('admin.suppliers.publish_on_import'))
                                ->helperText(__('admin.suppliers.publish_on_import_help')),
                            TextInput::make('config.min_cost')
                                ->label(__('admin.suppliers.min_cost'))
                                ->helperText(__('admin.suppliers.min_cost_help'))
                                ->numeric()
                                ->minValue(0)
                                ->suffix('₾'),
                            TextInput::make('config.rate')
                                ->label(__('admin.suppliers.rate'))
                                ->helperText(__('admin.suppliers.rate_help'))
                                ->integer()
                                ->minValue(1)
                                ->maxValue(600),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('priority')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['offers', 'stocks']))
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.suppliers.name'))
                    ->description(fn (Supplier $record) => $record->code)
                    ->searchable(['name', 'code'])
                    ->weight('bold'),
                TextColumn::make('driver')
                    ->label(__('admin.suppliers.driver'))
                    ->formatStateUsing(fn (string $state) => SupplierActions::drivers()[$state] ?? class_basename($state))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('offers_count')
                    ->label(__('admin.suppliers.offers'))
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('stocks_count')
                    ->label(__('admin.suppliers.stock_rows'))
                    ->alignCenter()
                    ->toggleable(),
                TextColumn::make('priority')
                    ->label(__('admin.suppliers.priority'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_run_at')
                    ->label(__('admin.suppliers.last_run_at'))
                    ->since()
                    ->placeholder(__('admin.suppliers.never'))
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label(__('admin.common.is_active')),
            ])
            ->recordActions([
                static::runAction()->hiddenLabel()->tooltip(__('admin.suppliers.run')),
                static::uploadAction()->hiddenLabel()->tooltip(__('admin.suppliers.upload')),
                EditAction::make(),
            ]);
    }

    public static function runAction(): Action
    {
        return Action::make('run')
            ->label(__('admin.suppliers.run'))
            ->icon(Heroicon::OutlinedPlay)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('admin.suppliers.run_confirm'))
            ->schema([
                Toggle::make('full')
                    ->label(__('admin.suppliers.run_full'))
                    ->helperText(__('admin.suppliers.run_full_help')),
            ])
            ->disabled(fn (Supplier $record) => ! $record->is_active)
            ->action(function (Supplier $record, array $data): void {
                try {
                    $queued = app(SupplierActions::class)->run($record, (bool) ($data['full'] ?? false));
                } catch (Throwable $e) {
                    Notification::make()->title(__('admin.suppliers.run_failed'))->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title(__('admin.suppliers.queued', ['count' => $queued]))
                    ->body(__('admin.suppliers.queue_hint', ['queue' => 'import:'.$record->code]))
                    ->success()
                    ->send();
            });
    }

    public static function uploadAction(): Action
    {
        return Action::make('upload')
            ->label(__('admin.suppliers.upload'))
            ->icon(Heroicon::OutlinedDocumentArrowUp)
            ->color('gray')
            ->visible(fn (Supplier $record) => SupplierActions::takesPriceLists($record))
            ->url(fn (Supplier $record) => ImportSpreadsheet::getUrl(['supplier' => $record->code]));
    }

    public static function stockAction(): Action
    {
        return Action::make('stock')
            ->label(__('admin.suppliers.stock'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (Supplier $record) => class_exists($record->driver) && is_subclass_of($record->driver, HasStockFeed::class))
            ->action(function (Supplier $record): void {
                try {
                    $count = app(SupplierActions::class)->refreshStock($record);
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title(__('admin.suppliers.stock_done', ['count' => $count]))->success()->send();
            });
    }

    public static function splitConfig(array $data): array
    {
        $config = (array) ($data['config'] ?? []);
        $common = array_intersect_key($config, array_flip(self::COMMON_CONFIG));
        $rest = array_diff_key($config, $common);

        return [
            ...$data,
            'config' => $common,
            'config_json' => $rest ? json_encode($rest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '{}',
        ];
    }

    public static function mergeConfig(array $data): array
    {
        $rest = json_decode((string) ($data['config_json'] ?? '{}'), true) ?: [];
        $common = array_filter(
            (array) ($data['config'] ?? []),
            fn ($value) => $value !== null && $value !== '',
        );

        foreach (['min_cost', 'rate'] as $number) {
            if (isset($common[$number])) {
                $common[$number] = $number === 'rate' ? (int) $common[$number] : (float) $common[$number];
            }
        }

        foreach (['require_stock', 'require_image', 'publish_on_import'] as $flag) {
            $common[$flag] = (bool) ($data['config'][$flag] ?? false);
        }

        unset($data['config_json'], $common['columns']);

        if ($columns = static::normalizeColumns((array) ($data['config']['columns'] ?? []))) {
            $common['columns'] = $columns;
        }

        return [
            ...$data,
            'config' => array_merge(is_array($rest) ? $rest : [], $common),
            'markup' => array_values(array_filter((array) ($data['markup'] ?? []), fn ($tier) => array_filter((array) $tier, fn ($value) => $value !== null && $value !== ''))),
        ];
    }

    public static function normalizeColumns(array $columns): array
    {
        if ($columns !== [] && is_array(reset($columns))) {
            $columns = Arr::pluck($columns, 'value', 'key');
        }

        return collect($columns)
            ->mapWithKeys(fn ($column, $role) => [trim((string) $role) => trim((string) $column)])
            ->filter(fn (string $column, string $role) => $role !== '' && $column !== '')
            ->map(fn (string $column, string $role) => $role === 'key_as' ? $column : strtoupper($column))
            ->all();
    }

    public static function getRelations(): array
    {
        return [
            OffersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}
