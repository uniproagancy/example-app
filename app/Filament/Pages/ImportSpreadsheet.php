<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Suppliers\SupplierResource;
use App\Filament\Support\AdminOnlyPage;
use App\Models\Supplier;
use App\Models\SupplierStock;
use App\Services\Import\PriceListLoader;
use App\Services\Import\SupplierActions;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;
use Throwable;
use UnitEnum;

class ImportSpreadsheet extends Page
{
    use AdminOnlyPage;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowUp;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'import/excel';

    #[Url(as: 'supplier', except: '')]
    public string $supplierCode = '';

    public ?array $data = [];

    public ?array $pending = null;

    public ?array $result = null;

    public ?string $batch = null;

    protected ?Collection $supplierList = null;

    protected array $statsCache = [];

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.import');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.excel.label');
    }

    public function getTitle(): string
    {
        return __('admin.excel.label');
    }

    public function getSubheading(): ?string
    {
        return __('admin.excel.subheading');
    }

    public function mount(): void
    {
        $supplier = $this->suppliers()->firstWhere('code', $this->supplierCode) ?? $this->suppliers()->first();

        $this->supplierCode = (string) $supplier?->code;

        $this->form->fill([
            'supplier_id' => $supplier?->id,
            'mode' => PriceListLoader::MODE_REPLACE,
            'sheet' => 1,
            'skip_rows' => 1,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('admin.excel.supplier'))
                    ->icon(Heroicon::OutlinedTruck)
                    ->schema([
                        Select::make('supplier_id')
                            ->label(__('admin.excel.supplier'))
                            ->hiddenLabel()
                            ->options(fn (): array => $this->suppliers()
                                ->mapWithKeys(fn (Supplier $supplier) => [$supplier->id => $supplier->is_active
                                    ? $supplier->name
                                    : $supplier->name.' · '.__('admin.excel.inactive')])
                                ->all())
                            ->required()
                            ->selectablePlaceholder(false)
                            ->live()
                            ->afterStateUpdated(function (): void {
                                $this->reset('pending', 'result', 'batch');
                                $this->supplierCode = (string) $this->supplier()?->code;
                            }),
                        Grid::make(['default' => 1, 'sm' => 3])
                            ->visible(fn (): bool => $this->supplier() !== null)
                            ->schema([
                                TextEntry::make('stats_rows')
                                    ->label(__('admin.excel.rows'))
                                    ->state(fn (): string => number_format($this->stats()['rows']))
                                    ->size('lg')
                                    ->weight('bold'),
                                TextEntry::make('stats_available')
                                    ->label(__('admin.excel.available'))
                                    ->state(fn (): string => number_format($this->stats()['available']))
                                    ->color('success')
                                    ->size('lg')
                                    ->weight('bold'),
                                TextEntry::make('stats_last')
                                    ->label(__('admin.excel.last_load'))
                                    ->state(fn (): string => $this->stats()['last'] ?? __('admin.excel.never'))
                                    ->size('lg')
                                    ->weight('bold'),
                            ]),
                        TextEntry::make('columns_map')
                            ->label(__('admin.excel.columns'))
                            ->visible(fn (): bool => $this->supplier() !== null)
                            ->state(fn (): array => collect($this->supplier()?->config['columns'] ?? [])
                                ->filter(fn ($column) => is_string($column) && $column !== '')
                                ->map(fn (string $column, string $role) => $role.': '.$column)
                                ->values()
                                ->all())
                            ->badge()
                            ->color('gray')
                            ->hintAction(
                                Action::make('editColumns')
                                    ->label(__('admin.excel.edit_columns'))
                                    ->icon(Heroicon::OutlinedPencilSquare)
                                    ->url(fn (): ?string => $this->supplier() ? SupplierResource::getUrl('edit', ['record' => $this->supplier()]) : null),
                            ),
                    ]),

                Section::make(__('admin.excel.file_section'))
                    ->icon(Heroicon::OutlinedTableCells)
                    ->visible(fn (): bool => $this->supplier() !== null)
                    ->columns(['default' => 1, 'md' => 4])
                    ->schema([
                        Radio::make('mode')
                            ->label(__('admin.excel.mode'))
                            ->options([
                                PriceListLoader::MODE_REPLACE => __('admin.excel.mode_replace'),
                                PriceListLoader::MODE_MERGE => __('admin.excel.mode_merge'),
                            ])
                            ->descriptions([
                                PriceListLoader::MODE_REPLACE => __('admin.excel.mode_replace_hint'),
                                PriceListLoader::MODE_MERGE => __('admin.excel.mode_merge_hint'),
                            ])
                            ->in(PriceListLoader::MODES)
                            ->required()
                            ->columns(['default' => 1, 'md' => 2])
                            ->columnSpanFull(),
                        FileUpload::make('file')
                            ->label(__('admin.excel.file'))
                            ->storeFiles(false)
                            ->maxSize(20480)
                            ->required()
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if ($value instanceof TemporaryUploadedFile && ! in_array(strtolower($value->getClientOriginalExtension()), PriceListLoader::FORMATS, true)) {
                                    $fail(__('admin.excel.wrong_format'));
                                }
                            })
                            ->columnSpan(['default' => 1, 'md' => 2]),
                        TextInput::make('sheet')
                            ->label(__('admin.excel.sheet'))
                            ->integer()
                            ->minValue(1)
                            ->maxValue(50)
                            ->required(),
                        TextInput::make('skip_rows')
                            ->label(__('admin.excel.skip_rows'))
                            ->integer()
                            ->minValue(0)
                            ->maxValue(20)
                            ->required(),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Callout::make(__('admin.excel.none'))
                ->description(__('admin.excel.none_help'))
                ->warning()
                ->visible(fn (): bool => $this->suppliers()->isEmpty())
                ->actions([
                    Action::make('suppliers')
                        ->label(__('admin.suppliers.plural'))
                        ->url(SupplierResource::getUrl()),
                ]),
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->visible(fn (): bool => $this->suppliers()->isNotEmpty())
                ->footer([
                    Actions::make([$this->loadAction()]),
                ]),
            Section::make(__('admin.excel.result'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->iconColor('success')
                ->description(fn (): ?string => $this->result['file'] ?? null)
                ->visible(fn (): bool => $this->result !== null)
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 3])->schema([
                        TextEntry::make('result_read')
                            ->label(__('admin.excel.loaded_rows'))
                            ->state(fn (): string => number_format($this->result['read'] ?? 0))
                            ->size('lg')
                            ->weight('bold'),
                        TextEntry::make('result_zeroed')
                            ->label(__('admin.excel.zeroed'))
                            ->state(fn (): string => number_format($this->result['zeroed'] ?? 0))
                            ->visible(fn (): bool => ($this->result['mode'] ?? null) === PriceListLoader::MODE_REPLACE)
                            ->color('danger')
                            ->size('lg')
                            ->weight('bold'),
                        TextEntry::make('result_kept')
                            ->label(__('admin.excel.mode'))
                            ->state(__('admin.excel.kept'))
                            ->visible(fn (): bool => ($this->result['mode'] ?? null) === PriceListLoader::MODE_MERGE),
                        TextEntry::make('result_duplicates')
                            ->label(__('admin.excel.duplicates'))
                            ->state(fn (): string => number_format($this->result['duplicates'] ?? 0))
                            ->visible(fn (): bool => ($this->result['duplicates'] ?? 0) > 0)
                            ->color('warning')
                            ->size('lg')
                            ->weight('bold'),
                    ]),
                    Callout::make(fn (): string => __('admin.excel.no_link', ['count' => $this->result['missing_url'] ?? 0]))
                        ->warning()
                        ->visible(fn (): bool => ($this->result['missing_url'] ?? 0) > 0),
                    Callout::make(__('admin.excel.no_stock_column'))
                        ->danger()
                        ->visible(fn (): bool => (bool) ($this->result['no_stock_column'] ?? false)),
                    Callout::make(__('admin.excel.next_step'))
                        ->info()
                        ->visible(fn (): bool => ($this->result['read'] ?? 0) > 0)
                        ->actions([$this->queueAction()]),
                ]),
        ]);
    }

    public function loadAction(): Action
    {
        return Action::make('load')
            ->label(__('admin.excel.load'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->mountUsing(function (?Schema $schema = null): void {
                $this->prepare();
                $schema?->fill();
            })
            ->modalHeading(__('admin.excel.confirm_title'))
            ->modalIcon(Heroicon::OutlinedDocumentArrowUp)
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel(fn (): string => __('admin.excel.confirm_yes', ['name' => $this->supplier()?->name]))
            ->schema([
                Callout::make(fn (): ?string => $this->supplier()?->name)
                    ->description(fn (): string => __('admin.excel.confirm_into', ['code' => $this->supplier()?->code]))
                    ->icon(Heroicon::OutlinedTruck)
                    ->color('primary'),
                Callout::make(fn (): string => __('admin.excel.confirm_other', ['name' => $this->pending['other'] ?? '']))
                    ->warning()
                    ->visible(fn (): bool => filled($this->pending['other'] ?? null)),
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    TextEntry::make('pending_file')
                        ->label(__('admin.excel.result_file'))
                        ->state(fn (): ?string => $this->pending['file'] ?? null),
                    TextEntry::make('pending_mode')
                        ->label(__('admin.excel.mode'))
                        ->state(fn (): string => __('admin.excel.mode_'.($this->data['mode'] ?? PriceListLoader::MODE_REPLACE)))
                        ->helperText(fn (): string => __('admin.excel.mode_'.($this->data['mode'] ?? PriceListLoader::MODE_REPLACE).'_hint')),
                    TextEntry::make('pending_sheet')
                        ->label(__('admin.excel.sheet'))
                        ->state(fn (): string => ($this->data['sheet'] ?? 1).' · '.__('admin.excel.skip_rows').': '.($this->data['skip_rows'] ?? 1)),
                    TextEntry::make('pending_now')
                        ->label(__('admin.excel.confirm_now'))
                        ->state(fn (): string => __('admin.excel.confirm_held', [
                            'rows' => number_format($this->stats()['rows']),
                            'when' => $this->stats()['last'] ?? __('admin.excel.never'),
                        ])),
                ]),
                RepeatableEntry::make('pending_rows')
                    ->label(fn (): string => __('admin.excel.preview', ['count' => number_format($this->pending['total'] ?? 0)]))
                    ->state(fn (): array => $this->pending['rows'] ?? [])
                    ->table([
                        TableColumn::make(__('admin.excel.preview_code')),
                        TableColumn::make(__('admin.excel.preview_name')),
                        TableColumn::make(__('admin.excel.preview_cost')),
                        TableColumn::make(__('admin.excel.preview_stock')),
                    ])
                    ->schema([
                        TextEntry::make('code')->weight('bold'),
                        TextEntry::make('name')->limit(60)->placeholder('—'),
                        TextEntry::make('cost')->placeholder('—'),
                        TextEntry::make('stock'),
                    ]),
            ])
            ->action(fn () => $this->load());
    }

    public function queueAction(): Action
    {
        return Action::make('queue')
            ->label(__('admin.excel.queue'))
            ->icon(Heroicon::OutlinedPlay)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(__('admin.excel.queue'))
            ->modalDescription(function (): string {
                $supplier = $this->resultSupplier();
                $scope = $this->batch
                    ? __('admin.excel.queue_merge', ['count' => number_format($this->result['read'] ?? 0)])
                    : __('admin.excel.queue_all');

                return $supplier && ! $supplier->is_active
                    ? $scope.' '.__('admin.excel.queue_enable', ['name' => $supplier->name])
                    : $scope;
            })
            ->action(function (): void {
                $supplier = $this->resultSupplier();

                if (! $supplier) {
                    return;
                }

                $codes = $this->batch ? Cache::get(self::batchKey($this->batch)) : null;

                if ($this->batch && $codes === null) {
                    Notification::make()->title(__('admin.excel.batch_expired'))->warning()->send();

                    return;
                }

                if (! $supplier->is_active) {
                    $supplier->update(['is_active' => true]);
                }

                try {
                    $queued = app(SupplierActions::class)->run($supplier, only: $codes);
                } catch (Throwable $e) {
                    Notification::make()->title(__('admin.suppliers.run_failed'))->body($e->getMessage())->danger()->send();

                    return;
                }

                $this->supplierList = null;

                Notification::make()
                    ->title(__('admin.suppliers.queued', ['count' => $queued]))
                    ->body(__('admin.suppliers.queue_hint', ['queue' => 'import:'.$supplier->code]))
                    ->success()
                    ->send();
            });
    }

    protected function prepare(): void
    {
        $this->pending = null;

        $state = $this->form->getState();
        $supplier = $this->supplier();
        $file = $this->upload();

        if (! $supplier || ! $file) {
            throw ValidationException::withMessages(['data.file' => __('admin.excel.nothing_read')]);
        }

        try {
            $preview = app(SupplierActions::class)->previewPriceList(
                $supplier,
                $file->getRealPath(),
                (int) $state['sheet'],
                (int) $state['skip_rows'],
                $file->getClientOriginalExtension(),
            );
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['data.file' => $e->getMessage()]);
        }

        if ($preview['total'] === 0) {
            throw ValidationException::withMessages(['data.file' => __('admin.excel.nothing_read')]);
        }

        $this->pending = [
            'supplier_id' => $supplier->id,
            'file' => $file->getClientOriginalName(),
            'other' => $this->otherSupplierNamed($supplier, $file->getClientOriginalName()),
            'total' => $preview['total'],
            'rows' => array_map(fn (array $row) => [
                'code' => $row['external_id'],
                'name' => $row['data']['name'] ?? $row['data']['model'] ?? $row['data']['url'] ?? null,
                'cost' => $row['cost_price'] === null ? null : number_format($row['cost_price'], 2).' ₾',
                'stock' => $row['quantity'],
            ], $preview['rows']),
        ];
    }

    protected function load(): void
    {
        $pending = $this->pending;
        $this->pending = null;

        $supplier = $this->supplier();
        $file = $this->upload();

        if (! $pending || ! $supplier || ! $file || $pending['supplier_id'] !== $supplier->id) {
            return;
        }

        $mode = in_array($this->data['mode'] ?? null, PriceListLoader::MODES, true) ? $this->data['mode'] : PriceListLoader::MODE_REPLACE;

        try {
            $result = app(SupplierActions::class)->loadPriceList(
                $supplier,
                $file->getRealPath(),
                (int) ($this->data['sheet'] ?? 1),
                (int) ($this->data['skip_rows'] ?? 1),
                $mode === PriceListLoader::MODE_MERGE,
                $file->getClientOriginalExtension(),
            );
        } catch (RuntimeException $e) {
            Notification::make()->title(__('admin.excel.failed'))->body($e->getMessage())->danger()->send();

            return;
        }

        $this->batch = null;

        if ($result['mode'] === PriceListLoader::MODE_MERGE && $result['codes']) {
            $this->batch = (string) Str::uuid();

            Cache::put(self::batchKey($this->batch), $result['codes'], now()->addHours(6));
        }

        unset($result['codes']);

        $this->result = [...$result, 'supplier_id' => $supplier->id, 'file' => $pending['file']];
        $this->statsCache = [];

        rescue(fn () => $file->delete(), report: false);

        $this->form->fill([
            'supplier_id' => $supplier->id,
            'mode' => $mode,
            'sheet' => (int) ($this->data['sheet'] ?? 1),
            'skip_rows' => (int) ($this->data['skip_rows'] ?? 1),
        ]);

        Notification::make()->title(__('admin.excel.loaded', ['count' => number_format($result['read'])]))->success()->send();
    }

    protected function upload(): ?TemporaryUploadedFile
    {
        return collect(Arr::wrap($this->data['file'] ?? null))
            ->first(fn ($file) => $file instanceof TemporaryUploadedFile);
    }

    protected function suppliers(): Collection
    {
        return $this->supplierList ??= Supplier::query()
            ->orderBy('name')
            ->get()
            ->filter(fn (Supplier $supplier) => SupplierActions::takesPriceLists($supplier))
            ->values();
    }

    protected function supplier(): ?Supplier
    {
        $id = (int) ($this->data['supplier_id'] ?? 0);

        return $id ? $this->suppliers()->firstWhere('id', $id) : null;
    }

    protected function resultSupplier(): ?Supplier
    {
        $id = (int) ($this->result['supplier_id'] ?? 0);

        return $id ? Supplier::query()->find($id) : null;
    }

    protected function stats(): array
    {
        $supplier = $this->supplier();

        if (! $supplier) {
            return ['rows' => 0, 'available' => 0, 'last' => null];
        }

        return $this->statsCache[$supplier->id] ??= (function () use ($supplier): array {
            $row = SupplierStock::query()
                ->where('supplier_id', $supplier->id)
                ->selectRaw('count(*) as total, sum(case when quantity > 0 then 1 else 0 end) as available, max(synced_at) as last_synced')
                ->first();

            return [
                'rows' => (int) $row?->total,
                'available' => (int) $row?->available,
                'last' => $row?->last_synced ? Carbon::parse($row->last_synced)->diffForHumans() : null,
            ];
        })();
    }

    protected function otherSupplierNamed(Supplier $chosen, string $fileName): ?string
    {
        $file = mb_strtolower(pathinfo($fileName, PATHINFO_FILENAME));

        if ($file === '') {
            return null;
        }

        $mentions = fn (Supplier $supplier): bool => collect([$supplier->code, $supplier->name])
            ->map(fn (?string $word) => mb_strtolower((string) $word))
            ->filter(fn (string $word) => mb_strlen($word) >= 3)
            ->contains(fn (string $word) => str_contains($file, $word));

        if ($mentions($chosen)) {
            return null;
        }

        return Supplier::query()->whereKeyNot($chosen->id)->get()->first($mentions)?->name;
    }

    protected static function batchKey(string $batch): string
    {
        return 'import:merged:'.$batch;
    }
}
