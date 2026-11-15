<?php

namespace App\Filament\Resources\Campaigns;

use App\Filament\Resources\Campaigns\Pages\ManageCampaigns;
use App\Filament\Support\AdminOnly;
use App\Filament\Support\CategoryOptions;
use App\Filament\Support\HasAdminLabels;
use App\Filament\Support\HasTrash;
use App\Filament\Support\Localized;
use App\Filament\Support\Schedule;
use App\Filament\Support\TrashActions;
use App\Models\Campaign;
use App\Models\Product;
use App\Support\Catalog;
use App\Support\CategoryTree;
use App\Support\WebpImage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CampaignResource extends Resource
{
    use AdminOnly;
    use HasAdminLabels;
    use HasTrash;

    protected static string $labelKey = 'campaigns';

    protected static string $groupKey = 'sales';

    protected static ?string $model = Campaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Localized::tabs('campaign-content', fn (string $locale, bool $isPrimary) => [
                    TextInput::make("title.$locale")
                        ->label(__('admin.common.title'))
                        ->required($isPrimary)
                        ->maxLength(120)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, Get $get, Set $set, string $operation) use ($isPrimary): void {
                            if ($isPrimary && $operation === 'create' && blank($get('slug'))) {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),
                    TextInput::make("badge.$locale")
                        ->label(__('admin.campaigns.badge'))
                        ->helperText(__('admin.campaigns.badge_help'))
                        ->maxLength(20),
                    Textarea::make("text.$locale")
                        ->label(__('admin.common.text'))
                        ->rows(3)
                        ->maxLength(1000),
                ]),
                TextInput::make('slug')
                    ->label(__('admin.campaigns.slug'))
                    ->helperText(__('admin.campaigns.slug_help'))
                    ->required()
                    ->maxLength(120)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->unique(ignoreRecord: true),
                TextInput::make('discount_percent')
                    ->label(__('admin.campaigns.discount'))
                    ->helperText(__('admin.campaigns.discount_help'))
                    ->integer()
                    ->minValue(1)
                    ->maxValue(90)
                    ->suffix('%'),
                Select::make('theme')
                    ->label(__('admin.common.theme'))
                    ->options(Localized::options('admin.themes', Campaign::THEMES))
                    ->default('navy')
                    ->required(),
                FileUpload::make('image')
                    ->label(__('admin.common.image'))
                    ->helperText(__('admin.campaigns.image_help'))
                    ->image()
                    ->disk('public')
                    ->directory('campaigns')
                    ->visibility('public')
                    ->saveUploadedFileUsing(WebpImage::uploader())
                    ->maxSize(5120)
                    ->columnSpanFull(),
                Section::make(__('admin.campaigns.products'))
                    ->description(__('admin.campaigns.products_help'))
                    ->schema([
                        Select::make('add_category')
                            ->label(__('admin.campaigns.add_category'))
                            ->helperText(__('admin.campaigns.add_category_help'))
                            ->options(fn () => CategoryOptions::paths())
                            ->searchable()
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                $node = $state ? CategoryTree::find((int) $state) : null;

                                if ($node) {
                                    $set('products', collect($get('products') ?? [])
                                        ->map(fn ($id) => (int) $id)
                                        ->merge(Catalog::productsIn($node)->pluck('id'))
                                        ->unique()
                                        ->values()
                                        ->all());
                                }

                                $set('add_category', null);
                            }),
                        Select::make('products')
                            ->label(__('admin.campaigns.products'))
                            ->multiple()
                            ->relationship('products', 'sku')
                            ->getOptionLabelFromRecordUsing(fn (Product $record) => static::productLabel($record))
                            ->getSearchResultsUsing(fn (string $search) => static::searchProducts($search))
                            ->searchable()
                            ->saveRelationshipsUsing(function (Campaign $record, ?array $state): void {
                                $record->products()->sync(collect($state ?? [])
                                    ->values()
                                    ->mapWithKeys(fn ($id, int $index) => [(int) $id => ['position' => $index]])
                                    ->all());
                                Catalog::flush();
                            }),
                    ])
                    ->columnSpanFull(),
                Schedule::fields(),
                Toggle::make('is_active')
                    ->label(__('admin.common.is_active'))
                    ->default(true),
            ]);
    }

    public static function searchProducts(string $search): array
    {
        $search = trim($search);

        if (mb_strlen($search) < 2) {
            return [];
        }

        return Product::query()
            ->with('kaTranslation')
            ->where(fn (Builder $query) => $query
                ->where('sku', 'like', "%{$search}%")
                ->orWhereHas('translations', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")))
            ->orderByDesc('is_active')
            ->limit(30)
            ->get()
            ->mapWithKeys(fn (Product $product) => [$product->id => static::productLabel($product)])
            ->all();
    }

    public static function productLabel(Product $product): string
    {
        $product->loadMissing('kaTranslation');

        return ($product->kaTranslation?->name ?? $product->sku).' · '.Catalog::money($product->price);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description(__('admin.campaigns.help'))
            ->defaultSort('position')
            ->reorderable('position')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('products'))
            ->columns([
                ImageColumn::make('image')
                    ->label(__('admin.common.image'))
                    ->disk('public')
                    ->imageHeight(44)
                    ->toggleable(),
                TextColumn::make('title')
                    ->label(__('admin.common.title'))
                    ->state(fn (Campaign $record) => Localized::value($record->title))
                    ->description(fn (Campaign $record) => '/promotions/'.$record->slug)
                    ->weight('bold')
                    ->wrap(),
                TextColumn::make('badge')
                    ->label(__('admin.campaigns.badge'))
                    ->state(fn (Campaign $record) => Localized::value($record->badge))
                    ->badge()
                    ->color('danger'),
                TextColumn::make('discount_percent')
                    ->label(__('admin.campaigns.discount'))
                    ->formatStateUsing(fn (?int $state) => $state ? '−'.$state.'%' : null)
                    ->placeholder('—')
                    ->badge()
                    ->color('success'),
                TextColumn::make('products_count')
                    ->label(__('admin.campaigns.products'))
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'primary' : 'gray'),
                Schedule::column(),
                ToggleColumn::make('is_active')
                    ->label(__('admin.common.is_active')),
            ])
            ->filters([
                TrashActions::filter(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('admin.campaigns.open'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (Campaign $record) => route('promotions.show', ['campaign' => $record->slug]))
                    ->openUrlInNewTab(),
                EditAction::make()->slideOver(),
                ...TrashActions::record(),
            ])
            ->toolbarActions([
                BulkActionGroup::make(TrashActions::bulk()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCampaigns::route('/'),
        ];
    }
}
