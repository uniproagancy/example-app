<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Support\CategoryOptions;
use App\Filament\Support\HasAdminLabels;
use App\Filament\Support\Localized;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class CategoryResource extends Resource
{
    use HasAdminLabels;

    protected static ?array $tree = null;

    protected static string $labelKey = 'categories';

    protected static string $groupKey = 'catalog';

    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 2;

    public static function getRecordTitle(?Model $record): string
    {
        return $record?->kaTranslation?->title ?? '#'.$record?->getKey();
    }

    public static function iconOptions(): array
    {
        return Localized::options('admin.icons', Category::ICONS);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(__('admin.common.content'))
                    ->schema([
                        Localized::relationTabs('category-translations', 'title', fn (string $locale, bool $isPrimary) => [
                            TextInput::make('title')
                                ->label(__('admin.categories.title'))
                                ->required($isPrimary)
                                ->maxLength(255),
                            TextInput::make('short_title')
                                ->label(__('admin.categories.short_title'))
                                ->maxLength(255),
                            TextInput::make('slug')
                                ->label(__('admin.common.slug'))
                                ->helperText(__('admin.common.slug_help'))
                                ->maxLength(255)
                                ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Str::slug($state) : null)
                                ->unique(
                                    table: CategoryTranslation::class,
                                    column: 'slug',
                                    ignoreRecord: true,
                                    modifyRuleUsing: fn (Unique $rule) => $rule->where('locale', $locale),
                                ),
                            Textarea::make('description')
                                ->label(__('admin.categories.description'))
                                ->rows(3),
                        ]),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make([
                    Section::make(__('admin.common.settings'))
                        ->schema([
                            Select::make('parent_id')
                                ->label(__('admin.categories.parent'))
                                ->helperText(__('admin.categories.parent_help'))
                                ->options(fn (?Category $record) => CategoryOptions::paths($record?->getKey()))
                                ->searchable()
                                ->live(),
                            Select::make('icon')
                                ->label(__('admin.common.icon'))
                                ->options(static::iconOptions())
                                ->placeholder(__('admin.categories.icon_auto'))
                                ->helperText(__('admin.categories.icon_help')),
                            TextInput::make('code')
                                ->label(__('admin.categories.code'))
                                ->helperText(__('admin.categories.code_help'))
                                ->alphaDash()
                                ->maxLength(64)
                                ->unique(ignoreRecord: true),
                            TextInput::make('position')
                                ->label(__('admin.common.position'))
                                ->integer()
                                ->default(0),
                            Toggle::make('is_active')
                                ->label(__('admin.common.is_active'))
                                ->default(true),
                            Toggle::make('show_on_main')
                                ->label(__('admin.categories.show_on_main'))
                                ->helperText(__('admin.categories.show_on_main_help'))
                                ->default(true)
                                ->visible(fn (Get $get) => blank($get('parent_id'))),
                            Toggle::make('in_feed')
                                ->label(__('admin.categories.in_feed'))
                                ->helperText(__('admin.categories.in_feed_help'))
                                ->default(true),
                            TextInput::make('google_category_id')
                                ->label(__('admin.categories.google_category'))
                                ->helperText(__('admin.categories.google_category_help'))
                                ->integer()
                                ->minValue(1)
                                ->maxValue(999999),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with('kaTranslation')
                ->withCount(['children', 'products'])
                ->orderByRaw(static::treeOrderSql($query)))
            ->paginated([50, 100, 'all'])
            ->defaultPaginationPageOption('all')
            ->columns([
                ViewColumn::make('kaTranslation.title')
                    ->label(__('admin.categories.title'))
                    ->view('filament.tables.columns.category-tree')
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('translations', fn (Builder $query) => $query->where('title', 'like', "%{$search}%"))),
                TextColumn::make('products_count')
                    ->label(__('admin.categories.products'))
                    ->state(fn (Category $record): ?int => $record->products_count ?: null)
                    ->placeholder('—')
                    ->badge()
                    ->color('primary')
                    ->description(fn (Category $record): ?string => $record->children_count
                        ? __('admin.categories.products_total', ['count' => number_format(static::tree()[$record->getKey()]['total'] ?? 0)])
                        : null)
                    ->alignCenter(),
                IconColumn::make('show_on_main')
                    ->label(__('admin.categories.show_on_main'))
                    ->state(fn (Category $record): ?bool => $record->parent_id ? null : $record->show_on_main)
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedMinusCircle)
                    ->falseColor('gray')
                    ->alignCenter(),
                ToggleColumn::make('is_active')
                    ->label(__('admin.common.is_active'))
                    ->alignCenter(),
                TextColumn::make('position')
                    ->label(__('admin.common.position'))
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('roots')
                    ->label(__('admin.categories.roots_only'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('parent_id'),
                        false: fn (Builder $query) => $query->whereNotNull('parent_id'),
                    ),
                SelectFilter::make('parent_id')
                    ->label(__('admin.categories.parent'))
                    ->options(fn () => CategoryOptions::paths())
                    ->searchable(),
            ])
            ->recordActions([
                static::addChildAction(),
                EditAction::make()->iconButton(),
                static::deleteAction()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    static::deleteBulkAction(),
                ]),
            ]);
    }

    public static function addChildAction(): Action
    {
        return Action::make('addChild')
            ->label(__('admin.categories.add_child'))
            ->iconButton()
            ->icon(Heroicon::OutlinedPlus)
            ->color('gray')
            ->tooltip(__('admin.categories.add_child'))
            ->modalHeading(fn (Category $record): string => __('admin.categories.add_child_to', ['name' => $record->kaTranslation?->title]))
            ->modalDescription(fn (Category $record): ?string => CategoryOptions::paths()[$record->getKey()] ?? null)
            ->modalIcon(Heroicon::OutlinedFolderPlus)
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel(__('admin.categories.add_child_submit'))
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('addAnother', arguments: ['another' => true])
                    ->label(__('admin.categories.add_another')),
            ])
            ->schema([
                TextInput::make('title')
                    ->label(__('admin.categories.title'))
                    ->required()
                    ->maxLength(255)
                    ->autofocus(),
                TextInput::make('title_en')
                    ->label(__('admin.categories.title_en'))
                    ->helperText(__('admin.categories.title_en_help'))
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label(__('admin.common.is_active'))
                    ->default(true),
            ])
            ->action(function (Category $record, array $data, array $arguments, Action $action, Schema $schema): void {
                $child = Category::createUnder($record->getKey(), $data['title'], $data['title_en'] ?? null, ['is_active' => (bool) ($data['is_active'] ?? true)]);

                static::flushTree();

                Notification::make()
                    ->title(__('admin.categories.child_created', ['name' => $data['title'], 'parent' => $record->kaTranslation?->title]))
                    ->actions([
                        Action::make('editChild')
                            ->label(__('admin.categories.edit_child'))
                            ->url(static::getUrl('edit', ['record' => $child])),
                    ])
                    ->success()
                    ->send();

                if ($arguments['another'] ?? false) {
                    $schema->fill(['is_active' => (bool) ($data['is_active'] ?? true)]);

                    $action->halt();
                }
            });
    }

    public static function tree(): array
    {
        if (static::$tree !== null) {
            return static::$tree;
        }

        $categories = Category::query()->orderBy('position')->orderBy('id')->get(['id', 'parent_id']);
        $parents = $categories->pluck('parent_id', 'id');
        $byParent = $categories->groupBy(fn (Category $category): int => (int) $category->parent_id);
        $tree = [];

        $walk = function (int $parentId, int $depth) use (&$walk, &$tree, $byParent): void {
            foreach ($byParent->get($parentId, collect()) as $category) {
                if (isset($tree[$category->id]) || $depth > 10) {
                    continue;
                }

                $tree[$category->id] = ['order' => count($tree), 'depth' => $depth, 'total' => 0];
                $walk($category->id, $depth + 1);
            }
        };

        $walk(0, 0);

        $counts = Product::query()
            ->whereNotNull('category_id')
            ->selectRaw('category_id, count(*) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id');

        foreach ($counts as $categoryId => $count) {
            for ($id = (int) $categoryId, $guard = 0; $id && isset($tree[$id]) && $guard < 12; $id = (int) $parents->get($id), $guard++) {
                $tree[$id]['total'] += (int) $count;
            }
        }

        return static::$tree = $tree;
    }

    public static function flushTree(): void
    {
        static::$tree = null;
        CategoryOptions::flush();
    }

    public static function treeIcon(Category $category): Heroicon
    {
        return match ($category->icon) {
            'mobile' => Heroicon::OutlinedDevicePhoneMobile,
            'computers' => Heroicon::OutlinedComputerDesktop,
            'tv' => Heroicon::OutlinedTv,
            'gaming' => Heroicon::OutlinedPuzzlePiece,
            'photo' => Heroicon::OutlinedCamera,
            'smart' => Heroicon::OutlinedHomeModern,
            'small' => Heroicon::OutlinedBolt,
            'acc' => Heroicon::OutlinedSquaresPlus,
            'headphones' => Heroicon::OutlinedMusicalNote,
            default => $category->children_count ? Heroicon::OutlinedFolder : Heroicon::OutlinedTag,
        };
    }

    protected static function treeOrderSql(Builder $query): string
    {
        $tree = static::tree();

        if ($tree === []) {
            return $query->qualifyColumn('id');
        }

        $cases = collect($tree)
            ->map(fn (array $node, int $id): string => 'WHEN '.$id.' THEN '.(int) $node['order'])
            ->implode(' ');

        return 'CASE '.$query->qualifyColumn('id').' '.$cases.' ELSE '.(count($tree) + 1).' END';
    }

    public static function deleteBulkAction(): DeleteBulkAction
    {
        return DeleteBulkAction::make()
            ->modalHeading(fn (EloquentCollection $records): string => __('admin.categories.bulk_delete_heading', ['count' => $records->count()]))
            ->modalDescription(__('admin.categories.bulk_delete_help'))
            ->using(function (EloquentCollection $records, DeleteBulkAction $action): void {
                $records
                    ->sortByDesc(fn (Category $category): int => count($category->ancestorIds()))
                    ->each(function (Category $category) use ($action): void {
                        if ($category->children()->exists() || $category->products()->exists()) {
                            $action->reportBulkProcessingFailure('in_use', fn (int $count): string => __('admin.categories.bulk_delete_kept', ['count' => $count]));

                            return;
                        }

                        $category->delete();
                    });

                static::flushTree();
            });
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->before(function (DeleteAction $action, Category $record): void {
                if ($record->children()->exists() || $record->products()->exists()) {
                    Notification::make()
                        ->title(__('admin.categories.cannot_delete'))
                        ->danger()
                        ->send();

                    $action->halt();
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
