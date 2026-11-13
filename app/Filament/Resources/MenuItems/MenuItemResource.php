<?php

namespace App\Filament\Resources\MenuItems;

use App\Filament\Resources\MenuItems\Pages\ManageMenuItems;
use App\Filament\Support\AdminOnly;
use App\Filament\Support\HasAdminLabels;
use App\Filament\Support\Localized;
use App\Models\MenuItem;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class MenuItemResource extends Resource
{
    use AdminOnly;
    use HasAdminLabels;

    protected static string $labelKey = 'menu_items';

    protected static string $groupKey = 'content';

    protected static ?string $model = MenuItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Localized::tabs('menu-title', fn (string $locale, bool $isPrimary) => [
                    TextInput::make("title.$locale")
                        ->label(__('admin.common.title'))
                        ->required($isPrimary)
                        ->maxLength(60),
                ]),
                Select::make('location')
                    ->label(__('admin.menu_items.location'))
                    ->options(Localized::options('admin.menu_items.locations', MenuItem::LOCATIONS))
                    ->default('header')
                    ->required(),
                Select::make('route_name')
                    ->label(__('admin.menu_items.route_name'))
                    ->helperText(__('admin.menu_items.route_help'))
                    ->options(Localized::options('admin.menu_items.routes', MenuItem::ROUTES)),
                TextInput::make('url')
                    ->label(__('admin.menu_items.url'))
                    ->helperText(__('admin.menu_items.url_help'))
                    ->maxLength(255)
                    ->requiredWithout('route_name'),
                Toggle::make('is_active')
                    ->label(__('admin.common.is_active'))
                    ->default(true)
                    ->inline(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->reorderable('position')
            ->defaultGroup(Group::make('location')
                ->label(__('admin.menu_items.location'))
                ->getTitleFromRecordUsing(fn (MenuItem $record) => __("admin.menu_items.locations.{$record->location}")))
            ->paginated(false)
            ->columns([
                TextColumn::make('title')
                    ->label(__('admin.common.title'))
                    ->state(fn (MenuItem $record) => Localized::value($record->title)),
                TextColumn::make('route_name')
                    ->label(__('admin.common.link'))
                    ->state(fn (MenuItem $record) => collect([
                        $record->route_name ? __("admin.menu_items.routes.{$record->route_name}") : null,
                        $record->url,
                    ])->filter()->implode(' ')),
                ToggleColumn::make('is_active')
                    ->label(__('admin.common.is_active')),
            ])
            ->filters([
                SelectFilter::make('location')
                    ->label(__('admin.menu_items.location'))
                    ->options(Localized::options('admin.menu_items.locations', MenuItem::LOCATIONS)),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMenuItems::route('/'),
        ];
    }
}
