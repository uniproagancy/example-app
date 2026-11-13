<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Support\AdminOnlyPage;
use App\Models\ProductOffer;
use App\Models\Supplier;
use App\Models\SupplierStock;
use App\Services\Import\Drivers\Citrus\CitrusLinks;
use App\Services\Import\ImportManager;
use App\Services\Import\ProductImporter;
use App\Support\Catalog;
use App\Support\Redact;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Throwable;
use UnitEnum;

class ImportLinks extends Page
{
    use AdminOnlyPage;

    public const SHOWN = 60;

    public const REASONS = 12;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'import/links';

    public ?array $data = [];

    #[Locked]
    public ?array $result = null;

    #[Locked]
    public bool $running = false;

    #[Locked]
    public bool $refreshing = false;

    #[Locked]
    public int $done = 0;

    #[Locked]
    public int $failed = 0;

    #[Locked]
    public int $total = 0;

    #[Locked]
    public string $current = '';

    #[Locked]
    public array $failures = [];

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.import');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.links.label');
    }

    public function getTitle(): string
    {
        return __('admin.links.label');
    }

    public function getSubheading(): ?string
    {
        return __('admin.links.subheading');
    }

    protected static function suppliers(): Collection
    {
        return Supplier::query()
            ->orderBy('name')
            ->get()
            ->filter(fn (Supplier $supplier): bool => empty($supplier->config['columns']['key']))
            ->values();
    }

    public function mount(): void
    {
        $suppliers = static::suppliers();

        $this->form->fill([
            'supplier_id' => ($suppliers->firstWhere('code', 'citrus') ?? $suppliers->first())?->id,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('admin.links.source'))
                    ->icon(Heroicon::OutlinedLink)
                    ->columns(2)
                    ->schema([
                        Select::make('supplier_id')
                            ->label(__('admin.links.supplier'))
                            ->options(fn (): array => static::suppliers()->pluck('name', 'id')->all())
                            ->required()
                            ->live()
                            ->disabled(fn (): bool => $this->running)
                            ->afterStateUpdated(fn () => $this->resetProgress()),
                        TextInput::make('category')
                            ->label(__('admin.links.category'))
                            ->placeholder(__('admin.links.category_hint'))
                            ->maxLength(120)
                            ->disabled(fn (): bool => $this->running),
                        Textarea::make('links')
                            ->label(__('admin.links.links'))
                            ->helperText(__('admin.links.separators'))
                            ->placeholder("https://citrus.ge/product/...\nhttps://citrus.ge/product/...")
                            ->rows(8)
                            ->required()
                            ->minLength(4)
                            ->maxLength(20000)
                            ->extraInputAttributes(['class' => 'font-mono text-xs'])
                            ->disabled(fn (): bool => $this->running)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->footer([
                    Actions::make([$this->addAction()]),
                ]),
            Callout::make(fn (): string => __('admin.links.added', ['added' => $this->result['added'] ?? 0, 'updated' => $this->result['updated'] ?? 0]))
                ->description(fn (): ?string => ($this->result['skipped'] ?? []) === []
                    ? null
                    : __('admin.links.skipped', ['count' => count($this->result['skipped'])]).' '.implode(' · ', array_slice($this->result['skipped'], 0, 10)))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color(fn (): string => ($this->result['skipped'] ?? []) === [] ? 'success' : 'warning')
                ->visible(fn (): bool => $this->result !== null),
            Section::make(__('admin.links.fetch_title'))
                ->description(__('admin.links.fetch_hint'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->schema([
                    Callout::make(fn (): string => __('admin.links.fetching', ['done' => $this->done + $this->failed, 'total' => $this->total]))
                        ->description(fn (): string => trim($this->current.' · '.$this->tally(), ' ·'))
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->info()
                        ->visible(fn (): bool => $this->running),
                    Callout::make(fn (): string => __('admin.links.finished', ['done' => $this->done, 'failed' => $this->failed]))
                        ->color(fn (): string => $this->failed ? 'warning' : 'success')
                        ->visible(fn (): bool => ! $this->running && ($this->done || $this->failed)),
                    Callout::make(__('admin.links.why'))
                        ->description(fn (): string => collect($this->failures)->map(fn (string $why, string $address): string => "$address — $why")->implode(' · '))
                        ->danger()
                        ->visible(fn (): bool => $this->failures !== []),
                    Actions::make([$this->fetchAction(), $this->refreshAction(), $this->stopAction()]),
                ]),
            Section::make(fn (): string => __('admin.links.queue', ['count' => $this->queueCount()]))
                ->icon(Heroicon::OutlinedQueueList)
                ->collapsible()
                ->schema([
                    View::make('filament.pages.import-links-queue')
                        ->viewData(fn (): array => [
                            'rows' => $this->queueRows(),
                            'more' => max(0, $this->queueCount() - self::SHOWN),
                            'locked' => $this->running,
                        ]),
                ]),
        ]);
    }

    public function addAction(): Action
    {
        return Action::make('addLinks')
            ->label(__('admin.links.add'))
            ->icon(Heroicon::OutlinedPlus)
            ->disabled(fn (): bool => $this->running)
            ->action(fn () => $this->add());
    }

    public function fetchAction(): Action
    {
        return Action::make('fetchLinks')
            ->label(fn (): string => __('admin.links.fetch', ['count' => $this->waitingCount()]))
            ->icon(Heroicon::OutlinedPlay)
            ->color('success')
            ->hidden(fn (): bool => $this->running)
            ->disabled(fn (): bool => $this->waitingCount() === 0)
            ->action(fn () => $this->start());
    }

    public function refreshAction(): Action
    {
        return Action::make('refreshLinks')
            ->label(fn (): string => __('admin.links.refresh', ['count' => $this->queueCount()]))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->hidden(fn (): bool => $this->running || $this->queueCount() === 0)
            ->requiresConfirmation()
            ->modalDescription(fn (): string => __('admin.links.refresh_confirm', ['count' => $this->queueCount()]))
            ->action(fn () => $this->start(refresh: true));
    }

    public function stopAction(): Action
    {
        return Action::make('stopLinks')
            ->label(__('admin.links.stop'))
            ->icon(Heroicon::OutlinedStop)
            ->color('danger')
            ->visible(fn (): bool => $this->running)
            ->action(fn () => $this->stop());
    }

    public function add(): void
    {
        $state = $this->form->getState();

        $this->result = app(CitrusLinks::class)->add($this->supplier(), (string) $state['links'], filled($state['category'] ?? null) ? trim($state['category']) : null);

        if ($this->result['added'] || $this->result['updated']) {
            $this->data['links'] = null;
        }
    }

    public function start(bool $refresh = false): void
    {
        $this->refreshing = $refresh;
        $this->done = 0;
        $this->failed = 0;
        $this->current = '';
        $this->failures = [];
        $this->total = $this->pending()->count();
        $this->running = $this->total > 0;

        if (! $this->running) {
            Notification::make()->title(__('admin.links.nothing'))->info()->send();

            return;
        }

        $this->fetchNext(app(ImportManager::class), app(ProductImporter::class));
    }

    public function stop(): void
    {
        $this->running = false;
        $this->current = '';
    }

    public function fetchNext(ImportManager $manager, ProductImporter $importer): void
    {
        if (! $this->running) {
            return;
        }

        $supplier = $this->supplier();
        $row = $this->pending()
            ->skip($this->refreshing ? $this->done + $this->failed : $this->failed)
            ->first();

        if (! $row) {
            $this->finish();

            return;
        }

        $this->current = $row->external_id;

        try {
            $payload = $manager->driver($supplier)->fetch($row->external_id);

            if (! $payload) {
                $this->lost($supplier, $row->external_id, __('admin.links.reason_page'));
            } elseif (! $importer->import($supplier, $payload, insist: true)) {
                $this->lost($supplier, $row->external_id, __('admin.links.reason_refused'));
            } else {
                $this->done++;
            }
        } catch (Throwable $e) {
            $this->lost($supplier, $row->external_id, Redact::secrets($e->getMessage()));
        }

        if ($this->done + $this->failed >= $this->total) {
            $this->finish();

            return;
        }

        $this->js('$wire.fetchNext()');
    }

    public function forget(int $id): void
    {
        if ($this->running) {
            return;
        }

        SupplierStock::query()
            ->where('supplier_id', $this->supplier()->id)
            ->whereKey($id)
            ->delete();

        Notification::make()->title(__('admin.links.forgotten'))->success()->send();
    }

    protected function queueRows(): Collection
    {
        $supplier = $this->selectedSupplier();

        if (! $supplier) {
            return collect();
        }

        $rows = SupplierStock::query()
            ->where('supplier_id', $supplier->id)
            ->latest('id')
            ->limit(self::SHOWN)
            ->get();

        $offers = ProductOffer::query()
            ->where('supplier_id', $supplier->id)
            ->whereIn('external_id', $rows->pluck('external_id'))
            ->with('product.kaTranslation')
            ->get()
            ->keyBy('external_id');

        return $rows->map(function (SupplierStock $row) use ($offers): array {
            $product = $offers->get($row->external_id)?->product;

            return [
                'id' => $row->id,
                'address' => $row->data['url'] ?? $row->external_id,
                'category' => $row->data['category'] ?? null,
                'product' => $product ? (string) ($product->kaTranslation?->name ?? $product->sku) : null,
                'edit' => $product ? ProductResource::getUrl('edit', ['record' => $product]) : null,
            ];
        });
    }

    protected function queueCount(): int
    {
        $supplier = $this->selectedSupplier();

        return $supplier ? SupplierStock::query()->where('supplier_id', $supplier->id)->count() : 0;
    }

    protected function waitingCount(): int
    {
        return $this->selectedSupplier() ? $this->waiting()->count() : 0;
    }

    protected function finish(): void
    {
        $this->running = false;
        $this->current = '';
        Catalog::flush();

        Notification::make()
            ->title(__('admin.links.finished', ['done' => $this->done, 'failed' => $this->failed]))
            ->{$this->failed ? 'warning' : 'success'}()
            ->send();
    }

    protected function lost(Supplier $supplier, string $externalId, string $why): void
    {
        $this->failed++;

        if (count($this->failures) < self::REASONS) {
            $this->failures[$externalId] = $why;
        }

        Log::channel('import')->warning('link import failed', [
            'supplier' => $supplier->code,
            'external' => $externalId,
            'error' => $why,
        ]);
    }

    protected function tally(): string
    {
        return trim(__('admin.links.done', ['count' => $this->done]).($this->failed ? ' · '.__('admin.links.failed', ['count' => $this->failed]) : ''));
    }

    protected function pending(): Builder
    {
        return $this->refreshing
            ? SupplierStock::query()->where('supplier_id', $this->supplier()->id)->orderBy('id')
            : $this->waiting();
    }

    protected function waiting(): Builder
    {
        $supplierId = $this->selectedSupplier()?->id ?? 0;

        return SupplierStock::query()
            ->where('supplier_id', $supplierId)
            ->whereNotIn('external_id', ProductOffer::query()->where('supplier_id', $supplierId)->select('external_id'))
            ->orderBy('id');
    }

    protected function resetProgress(): void
    {
        $this->reset(['result', 'running', 'refreshing', 'done', 'failed', 'total', 'current', 'failures']);
    }

    protected function selectedSupplier(): ?Supplier
    {
        $id = $this->data['supplier_id'] ?? null;

        return $id ? static::suppliers()->firstWhere('id', (int) $id) : null;
    }

    protected function supplier(): Supplier
    {
        return $this->selectedSupplier() ?? abort(422);
    }
}
