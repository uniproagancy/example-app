<?php

namespace App\Filament\Resources\Promos;

use App\Filament\Resources\Promos\Pages\ManagePromos;
use App\Filament\Support\AdminOnly;
use App\Filament\Support\CategoryOptions;
use App\Filament\Support\HasAdminLabels;
use App\Filament\Support\Localized;
use App\Filament\Support\Schedule;
use App\Models\Category;
use App\Models\Promo;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PromoResource extends Resource
{
    use AdminOnly;
    use HasAdminLabels;

    protected static string $labelKey = 'promos';

    protected static string $groupKey = 'content';

    protected static ?string $model = Promo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Localized::tabs('promo-content', fn (string $locale, bool $isPrimary) => [
                    Grid::make(2)->schema([
                        TextInput::make("badge.$locale")
                            ->label(__('admin.promos.badge'))
                            ->helperText(__('admin.promos.badge_help'))
                            ->maxLength(20),
                        TextInput::make("note.$locale")
                            ->label(__('admin.promos.note'))
                            ->helperText(__('admin.promos.note_help'))
                            ->maxLength(80),
                    ]),
                    TextInput::make("title.$locale")
                        ->label(__('admin.common.title'))
                        ->required($isPrimary)
                        ->maxLength(255),
                    Textarea::make("text.$locale")
                        ->label(__('admin.common.text'))
                        ->rows(2),
                ]),
                Select::make('category_id')
                    ->label(__('admin.common.category'))
                    ->options(fn () => CategoryOptions::paths())
                    ->searchable(),
                TextInput::make('url')
                    ->label(__('admin.common.url'))
                    ->helperText(__('admin.common.url_help'))
                    ->maxLength(255),
                Select::make('theme')
                    ->label(__('admin.common.theme'))
                    ->options(Localized::options('admin.themes', Promo::THEMES))
                    ->default('navy')
                    ->required(),
                Select::make('icon')
                    ->label(__('admin.common.icon'))
                    ->options(Localized::options('admin.icons', Category::ICONS)),
                Schedule::fields(),
                Toggle::make('is_active')
                    ->label(__('admin.common.is_active'))
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->reorderable('position')
            ->columns([
                TextColumn::make('badge')
                    ->label(__('admin.promos.badge'))
                    ->state(fn (Promo $record) => Localized::value($record->badge))
                    ->badge()
                    ->color('danger'),
                TextColumn::make('title')
                    ->label(__('admin.common.title'))
                    ->state(fn (Promo $record) => Localized::value($record->title))
                    ->description(fn (Promo $record) => Localized::value($record->text))
                    ->wrap(),
                TextColumn::make('theme')
                    ->label(__('admin.common.theme'))
                    ->formatStateUsing(fn (string $state) => __("admin.themes.$state"))
                    ->toggleable(),
                Schedule::column(),
                ToggleColumn::make('is_active')
                    ->label(__('admin.common.is_active')),
            ])
            ->recordActions([
                EditAction::make()->slideOver(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePromos::route('/'),
        ];
    }
}
