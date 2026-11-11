<?php

namespace App\Filament\Resources\Attributes;

use App\Filament\Resources\Attributes\Pages\CreateAttribute;
use App\Filament\Resources\Attributes\Pages\EditAttribute;
use App\Filament\Resources\Attributes\Pages\ListAttributes;
use App\Filament\Resources\Attributes\RelationManagers\ValuesRelationManager;
use App\Filament\Support\CategoryOptions;
use App\Filament\Support\HasAdminLabels;
use App\Filament\Support\Localized;
use App\Models\Attribute;
use App\Support\Catalog;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AttributeResource extends Resource
{
    use HasAdminLabels;

    protected static string $labelKey = 'attributes';

    protected static string $groupKey = 'catalog';

    protected static ?string $model = Attribute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFunnel;

    protected static ?int $navigationSort = 4;

    public static function getRecordTitle(?Model $record): string
    {
        return $record?->kaTranslation?->name ?? (string) $record?->code;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(__('admin.attributes.name'))
                    ->schema([
                        Localized::relationTabs('attribute-name', 'name', fn (string $locale, bool $isPrimary) => [
                            TextInput::make('name')
                                ->label(__('admin.attributes.name'))
                                ->required($isPrimary)
                                ->maxLength(255),
                        ]),
                        Select::make('categories')
                            ->label(__('admin.attributes.categories'))
                            ->helperText(__('admin.attributes.categories_help'))
                            ->multiple()
                            ->searchable()
                            ->options(fn () => CategoryOptions::paths())
                            ->afterStateHydrated(fn (Select $component, ?Attribute $record) => $component->state($record?->categories()->pluck('categories.id')->all() ?? []))
                            ->saveRelationshipsUsing(function (Attribute $record, ?array $state): void {
                                $record->categories()->sync($state ?? []);
                                Catalog::flush();
                            })
                            ->dehydrated(false),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make([
                    Section::make(__('admin.common.settings'))
                        ->schema([
                            TextInput::make('code')
                                ->label(__('admin.attributes.code'))
                                ->required()
                                ->alphaDash()
                                ->maxLength(64)
                                ->unique(ignoreRecord: true),
                            Select::make('type')
                                ->label(__('admin.attributes.type'))
                                ->options(Localized::options('admin.attributes.types', Attribute::TYPES))
                                ->default(Attribute::TYPE_SELECT)
                                ->required(),
                            TextInput::make('unit')
                                ->label(__('admin.attributes.unit'))
                                ->maxLength(16),
                            Toggle::make('is_filterable')
                                ->label(__('admin.attributes.is_filterable'))
                                ->helperText(__('admin.attributes.is_filterable_help')),
                            Toggle::make('is_variant')
                                ->label(__('admin.attributes.is_variant'))
                                ->helperText(__('admin.attributes.is_variant_help')),
                            TextInput::make('sort_order')
                                ->label(__('admin.common.position'))
                                ->integer()
                                ->default(100),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('kaTranslation')->withCount(['values', 'specs']))
            ->columns([
                TextColumn::make('kaTranslation.name')
                    ->label(__('admin.attributes.name'))
                    ->description(fn (Attribute $record) => $record->code)
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                        ->where('code', 'like', "%{$search}%")
                        ->orWhereHas('translations', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")))),
                TextColumn::make('type')
                    ->label(__('admin.attributes.type'))
                    ->formatStateUsing(fn (string $state) => __("admin.attributes.types.$state"))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('values_count')
                    ->label(__('admin.attributes.values_count'))
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('specs_count')
                    ->label(__('admin.attributes.specs_count'))
                    ->alignCenter()
                    ->sortable(),
                ToggleColumn::make('is_filterable')
                    ->label(__('admin.attributes.is_filterable')),
            ])
            ->filters([
                TernaryFilter::make('is_filterable')
                    ->label(__('admin.attributes.is_filterable')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ValuesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttributes::route('/'),
            'create' => CreateAttribute::route('/create'),
            'edit' => EditAttribute::route('/{record}/edit'),
        ];
    }
}
