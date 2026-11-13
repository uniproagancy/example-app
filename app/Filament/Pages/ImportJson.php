<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Support\AdminOnlyPage;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Import\Drivers\Alta\AltaJson;
use App\Services\Import\Drivers\Alta\AltaJsonAssets;
use App\Services\Import\Drivers\Alta\AltaPaste;
use App\Services\Import\ProductImporter;
use App\Services\Import\ProductPayload;
use App\Support\Catalog;
use App\Support\Figure;
use App\Support\Redact;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;
use UnitEnum;

class ImportJson extends Page
{
    use AdminOnlyPage;

    public const MAX_UPLOAD_KB = 10240;

    public const BATCH = 5;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCodeBracketSquare;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'import/json';

    public ?array $data = [];

    #[Locked]
    public ?string $sourceKey = null;

    #[Locked]
    public int $supplierId = 0;

    #[Locked]
    public int $total = 0;

    #[Locked]
    public int $valid = 0;

    #[Locked]
    public array $preview = [];

    #[Locked]
    public array $problems = [];

    #[Locked]
    public int $cursor = 0;

    #[Locked]
    public bool $saving = false;

    #[Locked]
    public int $saved = 0;

    #[Locked]
    public array $results = [];

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.import');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.json.label');
    }

    public function getTitle(): string
    {
        return __('admin.json.label');
    }

    public function getSubheading(): ?string
    {
        return __('admin.json.subheading');
    }

    public function mount(): void
    {
        $this->form->fill([
            'supplier_id' => Supplier::query()->where('code', 'alta')->value('id') ?? Supplier::query()->orderBy('name')->value('id'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('admin.json.source'))
                    ->icon(Heroicon::OutlinedCodeBracket)
                    ->schema([
                        Select::make('supplier_id')
                            ->label(__('admin.json.supplier'))
                            ->options(fn (): array => Supplier::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn () => $this->forget()),
                        Textarea::make('json')
                            ->label(__('admin.json.paste'))
                            ->helperText(__('admin.json.paste_help'))
                            ->rows(8)
                            ->extraInputAttributes(['class' => 'font-mono text-xs'])
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn () => $this->forget()),
                        FileUpload::make('file')
                            ->label(__('admin.json.file'))
                            ->helperText(__('admin.json.file_help', ['mb' => self::MAX_UPLOAD_KB / 1024]))
                            ->storeFiles(false)
                            ->maxSize(self::MAX_UPLOAD_KB)
                            ->live()
                            ->afterStateUpdated(fn () => $this->forget())
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if ($value instanceof TemporaryUploadedFile && ! in_array(strtolower($value->getClientOriginalExtension()), ['json', 'txt'], true)) {
                                    $fail(__('admin.json.wrong_file'));
                                }
                            }),
                    ]),
                Section::make(__('admin.json.corrections'))
                    ->description(__('admin.json.corrections_help'))
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->visible(fn (): bool => $this->alone())
                    ->columns(['default' => 1, 'sm' => 3])
                    ->schema([
                        TextInput::make('price')
                            ->label(__('admin.json.cost'))
                            ->numeric()
                            ->minValue(0)
                            ->suffix('₾')
                            ->live(onBlur: true),
                        TextInput::make('stock')
                            ->label(__('admin.json.stock'))
                            ->integer()
                            ->minValue(0),
                        TextEntry::make('selling')
                            ->label(__('admin.json.selling'))
                            ->state(fn (): ?string => $this->selling()),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->footer([
                    Actions::make([$this->lookAction(), $this->saveAction()]),
                ]),
            Callout::make(fn (): string => __('admin.json.summary', ['total' => $this->total, 'valid' => $this->valid]))
                ->description(fn (): ?string => $this->saving ? __('admin.json.saving', ['done' => $this->cursor, 'total' => $this->total]) : null)
                ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                ->color(fn (): string => $this->valid > 0 ? 'info' : 'warning')
                ->visible(fn (): bool => $this->sourceKey !== null),
            Section::make(__('admin.json.preview'))
                ->visible(fn (): bool => $this->preview !== [])
                ->schema([
                    RepeatableEntry::make('preview_rows')
                        ->hiddenLabel()
                        ->state(fn (): array => $this->preview)
                        ->table([
                            TableColumn::make(__('admin.json.code')),
                            TableColumn::make(__('admin.json.name')),
                            TableColumn::make(__('admin.json.category')),
                            TableColumn::make(__('admin.json.cost')),
                            TableColumn::make(__('admin.json.stock')),
                            TableColumn::make(__('admin.json.images')),
                            TableColumn::make(__('admin.json.state')),
                        ])
                        ->schema([
                            TextEntry::make('code')->weight('bold'),
                            TextEntry::make('name')->limit(60),
                            TextEntry::make('category')->placeholder('—'),
                            TextEntry::make('price'),
                            TextEntry::make('stock'),
                            TextEntry::make('images'),
                            TextEntry::make('state')
                                ->badge()
                                ->formatStateUsing(fn (string $state): string => __("admin.json.states.$state"))
                                ->color(fn (string $state): string => match ($state) {
                                    'new' => 'success',
                                    'update' => 'info',
                                    default => 'danger',
                                }),
                        ]),
                ]),
            Callout::make(__('admin.json.problems'))
                ->description(fn (): string => implode(' · ', $this->problems))
                ->warning()
                ->visible(fn (): bool => $this->problems !== []),
            Section::make(__('admin.json.saved', ['count' => $this->saved]))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->iconColor('success')
                ->visible(fn (): bool => $this->results !== [])
                ->schema([
                    RepeatableEntry::make('saved_rows')
                        ->hiddenLabel()
                        ->state(fn (): array => $this->results)
                        ->table([
                            TableColumn::make(__('admin.json.name')),
                            TableColumn::make(__('admin.json.price')),
                            TableColumn::make(__('admin.json.stock')),
                            TableColumn::make(__('admin.json.images')),
                        ])
                        ->schema([
                            TextEntry::make('name')
                                ->limit(60)
                                ->weight('bold'),
                            TextEntry::make('price'),
                            TextEntry::make('stock'),
                            TextEntry::make('images'),
                        ]),
                    TextEntry::make('saved_hint')
                        ->hiddenLabel()
                        ->state(__('admin.json.saved_hint')),
                ]),
        ]);
    }

    public function lookAction(): Action
    {
        return Action::make('look')
            ->label(__('admin.json.look'))
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->disabled(fn (): bool => $this->saving)
            ->action(fn () => $this->look());
    }

    public function saveAction(): Action
    {
        return Action::make('saveProducts')
            ->label(fn (): string => __('admin.json.save', ['count' => $this->valid]))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->visible(fn (): bool => $this->sourceKey !== null && $this->valid > 0)
            ->disabled(fn (): bool => $this->saving)
            ->requiresConfirmation(fn (): bool => $this->valid > 1)
            ->modalDescription(fn (): string => __('admin.json.save_confirm', ['count' => $this->valid]))
            ->action(fn () => $this->startSaving());
    }

    public function look(): void
    {
        $this->forget();
        $state = $this->form->getState();
        $supplier = Supplier::query()->findOrFail($state['supplier_id']);
        $answers = AltaPaste::split($this->source($state));

        if ($answers === []) {
            throw ValidationException::withMessages(['data.json' => __('admin.import.json_not_json')]);
        }

        $reader = new AltaJson($supplier);
        $known = [];

        foreach ($answers as $index => $answer) {
            $read = $this->read($reader, $answer);
            $this->total++;
            $this->valid += $read['payload'] ? 1 : 0;

            foreach ($read['errors'] as $error) {
                $this->problem($index + 1, $error);
            }

            if ($read['seen'] && count($this->preview) < 50) {
                $known[] = $supplier->code.'-'.$read['seen']['code'];
                $this->preview[] = [
                    'code' => (string) $read['seen']['code'],
                    'name' => (string) $read['seen']['name'],
                    'category' => $read['seen']['category'] ?? null,
                    'price' => Catalog::money($read['seen']['price']),
                    'raw_price' => (float) $read['seen']['price'],
                    'stock' => (int) $read['seen']['stock'],
                    'images' => count($read['seen']['images'] ?? []),
                    'state' => $read['payload'] ? 'new' : 'invalid',
                ];
            }
        }

        $existing = Product::query()->whereIn('sku', $known)->pluck('sku')->all();

        foreach ($this->preview as $i => $row) {
            if ($row['state'] === 'new' && in_array($supplier->code.'-'.$row['code'], $existing, true)) {
                $this->preview[$i]['state'] = 'update';
            }
        }

        $this->sourceKey = (string) Str::uuid();
        $this->supplierId = $supplier->id;
        Storage::disk('local')->put($this->path(), json_encode($answers, JSON_UNESCAPED_UNICODE));

        if ($this->alone()) {
            $this->data['price'] = (string) $this->preview[0]['raw_price'];
            $this->data['stock'] = (string) $this->preview[0]['stock'];
        }
    }

    public function startSaving(): void
    {
        if (! $this->sourceKey || ! Storage::disk('local')->exists($this->path())) {
            $this->forget();

            throw ValidationException::withMessages(['data.json' => __('admin.json.look_first')]);
        }

        if ($this->alone()) {
            $this->validate([
                'data.price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
                'data.stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
            ]);
        }

        $this->saving = true;
        $this->cursor = 0;
        $this->saved = 0;
        $this->results = [];
        $this->problems = [];

        $this->saveNext(app(ProductImporter::class));
    }

    public function saveNext(ProductImporter $importer): void
    {
        if (! $this->saving || ! $this->sourceKey || ! Storage::disk('local')->exists($this->path())) {
            return;
        }

        $answers = json_decode((string) Storage::disk('local')->get($this->path()), true) ?: [];
        $supplier = Supplier::query()->findOrFail($this->supplierId);
        $reader = new AltaJson($supplier);
        $assets = app(AltaJsonAssets::class);

        foreach (array_slice($answers, $this->cursor, self::BATCH, true) as $index => $answer) {
            $this->saveOne($importer, $supplier, $reader, $assets, (string) $answer, $index + 1, count($answers) === 1);
            $this->cursor = $index + 1;
        }

        if ($this->cursor < count($answers)) {
            $this->js('$wire.saveNext()');

            return;
        }

        $this->saving = false;
        Storage::disk('local')->delete($this->path());
        $this->sourceKey = null;
        $this->preview = [];
        $this->data['json'] = null;
        $this->data['file'] = null;
        Catalog::flush();

        Notification::make()
            ->title(__('admin.json.done', ['count' => $this->saved]))
            ->{$this->problems === [] ? 'success' : 'warning'}()
            ->send();
    }

    protected function saveOne(ProductImporter $importer, Supplier $supplier, AltaJson $reader, AltaJsonAssets $assets, string $answer, int $number, bool $alone): void
    {
        $read = $this->read($reader, $answer);

        if (! $read['payload']) {
            foreach ($read['errors'] as $error) {
                $this->problem($number, $error);
            }

            return;
        }

        $payload = $alone ? $this->corrected($read['payload']) : $read['payload'];

        try {
            if ($read['assets'] !== null) {
                $assets->store($supplier, $read['assets']);
            }

            $product = $importer->import($supplier, $payload, insist: true);
        } catch (Throwable $e) {
            Log::channel('import')->warning('pasted json could not be saved', [
                'supplier' => $supplier->code,
                'external' => $payload->externalId,
                'error' => Redact::secrets($e->getMessage()),
            ]);

            $this->problem($number, __('admin.json.failed', ['error' => Redact::secrets($e->getMessage())]));

            return;
        }

        if (! $product) {
            $this->problem($number, __('admin.json.refused'));

            return;
        }

        $this->saved++;
        $this->results[] = [
            'name' => (string) ($product->kaTranslation?->name ?? $product->sku),
            'price' => Catalog::money($product->price),
            'stock' => $product->stock,
            'images' => $product->images()->count(),
            'edit' => ProductResource::getUrl('edit', ['record' => $product]),
        ];
        $this->results = array_slice($this->results, -50);
    }

    protected function read(AltaJson $reader, string $answer): array
    {
        $read = $reader->read($answer);
        $read['assets'] = null;

        try {
            $read['assets'] = app(AltaJsonAssets::class)->validate($answer, $read['payload']?->images ?? []);
        } catch (ValidationException) {
            $read['errors'][] = __('admin.import.alta_invalid_export');
            $read['payload'] = null;
        }

        return $read;
    }

    protected function corrected(ProductPayload $payload): ProductPayload
    {
        $price = $this->data['price'] ?? null;
        $stock = $this->data['stock'] ?? null;

        return new ProductPayload(
            externalId: $payload->externalId,
            sku: $payload->sku,
            costPrice: $price === null || $price === '' ? $payload->costPrice : Figure::amount((string) $price),
            oldCostPrice: $payload->oldCostPrice,
            stock: $stock === null || $stock === '' ? $payload->stock : (int) $stock,
            brandName: $payload->brandName,
            categoryName: $payload->categoryName,
            translations: $payload->translations,
            specs: $payload->specs,
            images: $payload->images,
            weight: $payload->weight,
            variantGroup: $payload->variantGroup,
        );
    }

    protected function source(array $state): string
    {
        $file = collect(Arr::wrap($this->data['file'] ?? null))->first(fn ($item) => $item instanceof TemporaryUploadedFile);
        $pasted = trim((string) ($state['json'] ?? ''));

        if ($file && $pasted !== '') {
            throw ValidationException::withMessages(['data.file' => __('admin.json.one_source')]);
        }

        $source = $file ? (string) file_get_contents($file->getRealPath()) : $pasted;
        $source = (string) preg_replace('/^\xEF\xBB\xBF/', '', $source);

        if (strlen(trim($source)) < 20) {
            throw ValidationException::withMessages([$file ? 'data.file' : 'data.json' => __('admin.import.json_not_json')]);
        }

        return $source;
    }

    public function alone(): bool
    {
        return $this->sourceKey !== null && $this->total === 1 && $this->valid === 1;
    }

    protected function selling(): ?string
    {
        $supplier = $this->supplierId ? Supplier::query()->find($this->supplierId) : null;
        $price = $this->data['price'] ?? null;

        if (! $supplier || $price === null || $price === '' || ! is_numeric($price)) {
            return null;
        }

        return Catalog::money($supplier->markup((float) $price));
    }

    protected function problem(int $number, string $message): void
    {
        $this->problems[] = __('admin.json.in_answer', ['n' => $number]).' '.$message;
        $this->problems = array_slice($this->problems, -20);
    }

    protected function forget(): void
    {
        if ($this->sourceKey) {
            Storage::disk('local')->delete($this->path());
        }

        $this->reset(['sourceKey', 'supplierId', 'total', 'valid', 'preview', 'problems', 'cursor', 'saving', 'saved', 'results']);
        $this->resetErrorBag();
    }

    protected function path(): string
    {
        return 'json-imports/'.$this->sourceKey.'.json';
    }
}
