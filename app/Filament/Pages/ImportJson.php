<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Support\AdminOnlyPage;
use App\Http\Controllers\JsonUploadController;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Import\Drivers\Alta\AltaJson;
use App\Services\Import\Drivers\Alta\AltaJsonAssets;
use App\Services\Import\Drivers\Alta\AltaPaste;
use App\Services\Import\Drivers\Zoommer\ZoommerDriver;
use App\Services\Import\Drivers\Zoommer\ZoommerJson;
use App\Services\Import\ImageDownloader;
use App\Services\Import\JsonObjectStream;
use App\Services\Import\ProductImporter;
use App\Services\Import\ProductPayload;
use App\Support\Catalog;
use App\Support\Figure;
use App\Support\Redact;
use BackedEnum;
use Filament\Actions\Action;
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
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use SplFileObject;
use Throwable;
use UnitEnum;

class ImportJson extends Page
{
    use AdminOnlyPage;

    public const MAX_UPLOAD_KB = 512000;

    public const MEMORY_LIMIT = '-1';

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

    #[Locked]
    public ?string $upload = null;

    #[Locked]
    public ?string $uploadName = null;

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
                        View::make('filament.pages.import-json-upload'),
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

    public function uploaded(?string $upload, ?string $name): void
    {
        $this->forget();

        if ($upload === null) {
            $this->upload = null;
            $this->uploadName = null;

            return;
        }

        if (! Str::isUuid($upload) || ! Storage::disk('local')->exists(JsonUploadController::path((int) auth()->id(), $upload))) {
            throw ValidationException::withMessages(['data.file' => __('admin.json.upload_failed')]);
        }

        if (! in_array(strtolower(pathinfo((string) $name, PATHINFO_EXTENSION)), ['json', 'txt'], true)) {
            throw ValidationException::withMessages(['data.file' => __('admin.json.wrong_file')]);
        }

        $this->upload = $upload;
        $this->uploadName = Str::limit((string) $name, 120);
    }

    public function look(): void
    {
        ini_set('memory_limit', self::MEMORY_LIMIT);
        set_time_limit(0);
        $this->forget();
        $state = $this->form->getState();
        $supplier = Supplier::query()->findOrFail($state['supplier_id']);
        $reader = $this->reader($supplier);
        $known = [];
        $this->sourceKey = (string) Str::uuid();
        $disk = Storage::disk('local');
        $disk->makeDirectory('json-imports');
        $output = fopen($disk->path($this->path()), 'wb');

        try {
            $this->answers($state, function (string $answer) use ($output, $reader, $supplier, &$known): void {
                fwrite($output, str_replace(["\r", "\n"], ' ', $answer)."\n");
                $read = $this->read($reader, $answer);
                $this->total++;
                $this->valid += $read['payload'] ? 1 : 0;

                foreach ($read['errors'] as $error) {
                    $this->problem($this->total, $error);
                }

                if ($read['seen'] && count($this->preview) < 50) {
                    $sku = (string) ($read['seen']['sku'] ?? $supplier->code.'-'.$read['seen']['code']);
                    $known[] = $sku;
                    $this->preview[] = [
                        'sku' => $sku,
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
            });
        } catch (Throwable $e) {
            fclose($output);
            $this->forget();

            throw $e;
        }

        fclose($output);

        if ($this->total === 0) {
            $field = $this->upload ? 'data.file' : 'data.json';
            $this->forget();

            throw ValidationException::withMessages([$field => __('admin.import.json_not_json')]);
        }

        $existing = Product::query()->whereIn('sku', $known)->pluck('sku')->all();

        foreach ($this->preview as $i => $row) {
            if ($row['state'] === 'new' && in_array($row['sku'], $existing, true)) {
                $this->preview[$i]['state'] = 'update';
            }
        }

        $this->supplierId = $supplier->id;

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

        ini_set('memory_limit', self::MEMORY_LIMIT);

        $supplier = Supplier::query()->findOrFail($this->supplierId);
        $reader = $this->reader($supplier);
        $assets = app(AltaJsonAssets::class);
        $file = new SplFileObject(Storage::disk('local')->path($this->path()));
        $file->seek($this->cursor);
        $last = min($this->total, $this->cursor + self::BATCH);

        while ($this->cursor < $last && ! $file->eof()) {
            $answer = rtrim((string) $file->current(), "\r\n");
            $file->next();
            $this->saveOne($importer, $supplier, $reader, $assets, $answer, $this->cursor + 1, $this->total === 1);
            $this->cursor++;
        }

        $file = null;

        if ($this->cursor < $this->total) {
            $this->js('$wire.saveNext()');

            return;
        }

        $this->saving = false;
        Storage::disk('local')->delete($this->path());
        $this->sourceKey = null;
        $this->preview = [];
        $this->data['json'] = null;

        if ($this->upload) {
            Storage::disk('local')->delete(JsonUploadController::path((int) auth()->id(), $this->upload));
        }

        $this->upload = null;
        $this->uploadName = null;
        Catalog::flush();

        Notification::make()
            ->title(__('admin.json.done', ['count' => $this->saved]))
            ->{$this->problems === [] ? 'success' : 'warning'}()
            ->send();
    }

    protected function saveOne(ProductImporter $importer, Supplier $supplier, AltaJson|ZoommerJson $reader, AltaJsonAssets $assets, string $answer, int $number, bool $alone): void
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

            $import = fn () => $importer->import($supplier, $payload, insist: true);
            $product = $read['given'] !== null ? ImageDownloader::using($read['given'], $import) : $import();
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

    protected function reader(Supplier $supplier): AltaJson|ZoommerJson
    {
        return $supplier->driver === ZoommerDriver::class ? new ZoommerJson($supplier) : new AltaJson($supplier);
    }

    protected function read(AltaJson|ZoommerJson $reader, string $answer): array
    {
        $read = $reader->read($answer);
        $read['assets'] = null;
        $read['given'] = null;

        if ($reader instanceof ZoommerJson) {
            try {
                $read['given'] = $read['payload'] ? $reader->assets($answer, $read['payload']->images) : null;
            } catch (ValidationException) {
                $read['errors'][] = __('admin.import.zoommer_invalid_export');
                $read['payload'] = null;
            }

            return $read;
        }

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

    /**
     * @param  callable(string): void  $emit
     */
    protected function answers(array $state, callable $emit): void
    {
        $pasted = trim((string) ($state['json'] ?? ''));

        if ($this->upload && $pasted !== '') {
            throw ValidationException::withMessages(['data.file' => __('admin.json.one_source')]);
        }

        if ($this->upload) {
            $path = JsonUploadController::path((int) auth()->id(), $this->upload);

            if (! Storage::disk('local')->exists($path)) {
                $this->upload = null;
                $this->uploadName = null;

                throw ValidationException::withMessages(['data.file' => __('admin.json.upload_failed')]);
            }

            (new JsonObjectStream)->each(Storage::disk('local')->path($path), $emit);

            return;
        }

        $source = (string) preg_replace('/^\xEF\xBB\xBF/', '', $pasted);

        if (strlen(trim($source)) < 20) {
            throw ValidationException::withMessages(['data.json' => __('admin.import.json_not_json')]);
        }

        foreach (AltaPaste::split($source) as $answer) {
            $emit($answer);
        }
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

        return Catalog::money((float) $price);
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
