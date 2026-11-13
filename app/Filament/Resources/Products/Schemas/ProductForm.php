<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Filament\Support\AttributeOptions;
use App\Filament\Support\CategoryOptions;
use App\Filament\Support\Localized;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductTranslation;
use App\Services\Import\TaxonomyResolver;
use App\Support\Slug;
use App\Support\VariantGroups;
use App\Support\WebpImage;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make([
                    Section::make(__('admin.products.translations'))
                        ->schema([
                            Localized::relationTabs('product-translations', 'name', fn (string $locale, bool $isPrimary) => [
                                TextInput::make('name')
                                    ->label(__('admin.products.name'))
                                    ->required($isPrimary)
                                    ->maxLength(255),
                                TextInput::make('slug')
                                    ->label(__('admin.common.slug'))
                                    ->helperText(__('admin.common.slug_help'))
                                    ->maxLength(255)
                                    ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Str::slug($state) : null)
                                    ->unique(
                                        table: ProductTranslation::class,
                                        column: 'slug',
                                        ignoreRecord: true,
                                        modifyRuleUsing: fn (Unique $rule) => $rule->where('locale', $locale),
                                    ),
                                TextInput::make('subtitle')
                                    ->label(__('admin.products.subtitle'))
                                    ->maxLength(255),
                                Textarea::make('description')
                                    ->label(__('admin.products.description'))
                                    ->rows(6),
                            ]),
                        ]),

                    Section::make(__('admin.products.images'))
                        ->description(__('admin.products.images_help'))
                        ->schema([
                            Repeater::make('images')
                                ->hiddenLabel()
                                ->relationship()
                                ->orderColumn('position')
                                ->reorderableWithDragAndDrop()
                                ->grid(['default' => 2, 'md' => 3, 'xl' => 4])
                                ->defaultItems(0)
                                ->addActionLabel(__('admin.common.image'))
                                ->simple(
                                    FileUpload::make('path')
                                        ->image()
                                        ->disk('public')
                                        ->directory('products')
                                        ->visibility('public')
                                        ->saveUploadedFileUsing(WebpImage::uploader())
                                        ->maxSize(5120)
                                        ->imageEditor()
                                        ->required(),
                                ),
                        ]),

                    Section::make(__('admin.products.specs'))
                        ->collapsible()
                        ->schema([
                            Repeater::make('specs')
                                ->hiddenLabel()
                                ->relationship(modifyQueryUsing: fn (Builder $query) => $query->with(['kaTranslation', 'enTranslation']))
                                ->orderColumn('position')
                                ->reorderableWithDragAndDrop()
                                ->defaultItems(0)
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string => filled($state['attribute_id'] ?? null) ? (AttributeOptions::all()[$state['attribute_id']] ?? null) : null)
                                ->addActionLabel(__('admin.products.spec'))
                                ->columns(3)
                                ->schema([
                                    Select::make('attribute_id')
                                        ->label(__('admin.product_import.spec_attribute'))
                                        ->options(fn () => AttributeOptions::all())
                                        ->searchable()
                                        ->required()
                                        ->distinct()
                                        ->createOptionForm([
                                            TextInput::make('name')->label(__('admin.attributes.name'))->required()->maxLength(255),
                                        ])
                                        ->createOptionUsing(fn (array $data): int => static::createAttribute($data['name'])),
                                    Group::make([
                                        TextInput::make('value')
                                            ->label(__('admin.products.spec_value').' · '.__('admin.locales.ka'))
                                            ->required()
                                            ->maxLength(255),
                                    ])
                                        ->relationship('kaTranslation')
                                        ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => [...$data, 'locale' => 'ka']),
                                    Group::make([
                                        TextInput::make('value')
                                            ->label(__('admin.products.spec_value').' · '.__('admin.locales.en'))
                                            ->maxLength(255),
                                    ])
                                        ->relationship('enTranslation', condition: fn (?array $state): bool => filled($state['value'] ?? null))
                                        ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => [...$data, 'locale' => 'en']),
                                    Toggle::make('is_key')
                                        ->label(__('admin.product_import.is_key')),
                                ]),
                        ]),

                    Section::make(__('admin.product_import.filters'))
                        ->description(__('admin.product_import.filters_help'))
                        ->collapsible()
                        ->schema([
                            Select::make('attributeValues')
                                ->hiddenLabel()
                                ->multiple()
                                ->relationship('attributeValues', 'code', modifyQueryUsing: fn (Builder $query) => $query->with(['kaTranslation', 'attribute.kaTranslation']))
                                ->getOptionLabelFromRecordUsing(fn (AttributeValue $value) => static::valueLabel($value))
                                ->searchable()
                                ->getSearchResultsUsing(fn (string $search) => AttributeValue::query()
                                    ->whereHas('attribute', fn (Builder $query) => $query->where('is_filterable', true)->orWhere('is_variant', true))
                                    ->where(fn (Builder $query) => $query
                                        ->where('code', 'like', "%{$search}%")
                                        ->orWhereHas('translations', fn (Builder $query) => $query->where('label', 'like', "%{$search}%")))
                                    ->with(['kaTranslation', 'attribute.kaTranslation'])
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn (AttributeValue $value) => [$value->id => static::valueLabel($value)])
                                    ->all()),
                        ]),

                    Section::make(__('admin.product_variants.title'))
                        ->description(fn (): string => __('admin.product_variants.help', [
                            'attributes' => Attribute::query()->where('is_variant', true)->with('kaTranslation')->get()
                                ->map(fn (Attribute $attribute) => $attribute->kaTranslation?->name ?: $attribute->code)
                                ->implode(', ') ?: __('admin.product_variants.none_marked'),
                        ]))
                        ->collapsible()
                        ->schema([
                            Select::make('variant_siblings')
                                ->label(__('admin.product_variants.siblings'))
                                ->multiple()
                                ->searchable()
                                ->dehydrated(false)
                                ->afterStateHydrated(fn (Select $component, ?Product $record) => $component->state($record ? VariantGroups::siblingIds($record) : []))
                                ->getSearchResultsUsing(fn (string $search, ?Product $record): array => Product::query()
                                    ->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
                                    ->where(fn (Builder $query) => $query
                                        ->where('sku', 'like', "%{$search}%")
                                        ->orWhereHas('translations', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")))
                                    ->with('kaTranslation')
                                    ->limit(30)
                                    ->get()
                                    ->mapWithKeys(fn (Product $product) => [$product->id => static::productLabel($product)])
                                    ->all())
                                ->getOptionLabelsUsing(fn (array $values): array => Product::query()
                                    ->whereKey($values)
                                    ->with('kaTranslation')
                                    ->get()
                                    ->mapWithKeys(fn (Product $product) => [$product->id => static::productLabel($product)])
                                    ->all()),
                        ]),

                    Section::make(__('admin.products.options'))
                        ->description(__('admin.products.options_help'))
                        ->collapsible()
                        ->schema([
                            Repeater::make('options')
                                ->hiddenLabel()
                                ->relationship()
                                ->orderColumn('position')
                                ->reorderableWithDragAndDrop()
                                ->defaultItems(0)
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string => $state['label']['ka'] ?? null)
                                ->addActionLabel(__('admin.products.option'))
                                ->columns(4)
                                ->schema([
                                    Select::make('type')
                                        ->label(__('admin.products.option_type'))
                                        ->options(Localized::options('admin.products.option_types', ProductOption::TYPES))
                                        ->default('color')
                                        ->required()
                                        ->live(),
                                    TextInput::make('label.ka')
                                        ->label(__('admin.products.option_label').' · '.__('admin.locales.ka'))
                                        ->required(),
                                    TextInput::make('label.en')
                                        ->label(__('admin.products.option_label').' · '.__('admin.locales.en')),
                                    ColorPicker::make('hex')
                                        ->label(__('admin.products.hex'))
                                        ->visible(fn (Get $get): bool => $get('type') === 'color'),
                                    TextInput::make('price')
                                        ->label(__('admin.products.option_price'))
                                        ->numeric()
                                        ->minValue(0)
                                        ->suffix('₾'),
                                    TextInput::make('old_price')
                                        ->label(__('admin.products.option_old_price'))
                                        ->numeric()
                                        ->minValue(0)
                                        ->suffix('₾'),
                                    Toggle::make('is_default')
                                        ->label(__('admin.products.is_default'))
                                        ->inline(false),
                                ]),
                        ]),
                ])->columnSpan(['lg' => 2]),

                Group::make([
                    Section::make(__('admin.products.pricing'))
                        ->schema([
                            TextInput::make('price')
                                ->label(__('admin.products.price'))
                                ->numeric()
                                ->minValue(0)
                                ->suffix('₾')
                                ->required(),
                            TextInput::make('old_price')
                                ->label(__('admin.products.old_price'))
                                ->helperText(__('admin.products.old_price_help'))
                                ->numeric()
                                ->suffix('₾')
                                ->gt('price'),
                            TextInput::make('stock')
                                ->label(__('admin.products.stock'))
                                ->integer()
                                ->minValue(0)
                                ->default(0)
                                ->required(),
                        ]),

                    Section::make(__('admin.products.main'))
                        ->schema([
                            Select::make('category_id')
                                ->label(__('admin.common.category'))
                                ->options(fn () => CategoryOptions::paths())
                                ->searchable()
                                ->required(),
                            Select::make('brand_id')
                                ->label(__('admin.products.brand'))
                                ->relationship('brand', 'name')
                                ->searchable()
                                ->preload()
                                ->createOptionForm([
                                    TextInput::make('name')
                                        ->label(__('admin.brands.name'))
                                        ->required()
                                        ->maxLength(255),
                                ]),
                            TextInput::make('sku')
                                ->label(__('admin.products.sku'))
                                ->required()
                                ->maxLength(64)
                                ->unique(ignoreRecord: true),
                            TextInput::make('supplier_sku')
                                ->label(__('admin.products.supplier_sku'))
                                ->maxLength(64),
                        ]),

                    Section::make(__('admin.product_import.section'))
                        ->schema([
                            TextInput::make('cost_price')
                                ->label(__('admin.product_import.cost_price'))
                                ->suffix('₾')
                                ->disabled()
                                ->dehydrated(false)
                                ->placeholder(__('admin.common.none')),
                            Toggle::make('price_lock')
                                ->label(__('admin.product_import.price_lock'))
                                ->helperText(__('admin.product_import.price_lock_help')),
                            Toggle::make('taxonomy_lock')
                                ->label(__('admin.product_import.taxonomy_lock'))
                                ->helperText(__('admin.product_import.taxonomy_lock_help')),
                        ]),

                    Section::make(__('admin.products.visibility'))
                        ->schema([
                            Toggle::make('is_active')
                                ->label(__('admin.common.is_active'))
                                ->helperText(__('admin.products.is_active_help'))
                                ->default(true),
                            Toggle::make('is_new')
                                ->label(__('admin.products.is_new')),
                            TextInput::make('position')
                                ->label(__('admin.common.position'))
                                ->helperText(__('admin.products.position_help'))
                                ->integer()
                                ->default(0),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ]);
    }

    public static function productLabel(Product $product): string
    {
        return trim(($product->kaTranslation?->name ?? '').' · '.$product->sku, ' ·');
    }

    public static function valueLabel(AttributeValue $value): string
    {
        return ($value->attribute?->kaTranslation?->name ?? $value->attribute?->code).': '.($value->kaTranslation?->label ?? $value->code);
    }

    public static function createAttribute(string $name): int
    {
        $attribute = Attribute::query()->firstOrCreate(
            ['code' => TaxonomyResolver::fitTo(Slug::make($name) ?: 'spec-'.substr(md5($name), 0, 8))],
            ['type' => Attribute::TYPE_SELECT, 'sort_order' => 100],
        );

        if ($attribute->wasRecentlyCreated) {
            $attribute->saveTranslations(['ka' => ['name' => $name]]);
        }

        AttributeOptions::flush();

        return $attribute->id;
    }
}
