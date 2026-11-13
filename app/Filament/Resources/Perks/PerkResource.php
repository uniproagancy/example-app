<?php

namespace App\Filament\Resources\Perks;

use App\Filament\Resources\Perks\Pages\ManagePerks;
use App\Filament\Support\AdminOnly;
use App\Filament\Support\HasAdminLabels;
use App\Filament\Support\Localized;
use App\Models\Perk;
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
use Filament\Tables\Table;

class PerkResource extends Resource
{
    use AdminOnly;
    use HasAdminLabels;

    protected static string $labelKey = 'perks';

    protected static string $groupKey = 'home';

    protected static ?string $model = Perk::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Localized::tabs('perk-content', fn (string $locale, bool $isPrimary) => [
                TextInput::make("title.$locale")
                    ->label(__('admin.common.title'))
                    ->required($isPrimary)
                    ->maxLength(120),
                TextInput::make("text.$locale")
                    ->label(__('admin.common.text'))
                    ->maxLength(255),
            ]),
            Select::make('icon')
                ->label(__('admin.common.icon'))
                ->options(Localized::options('admin.icons', Perk::ICONS))
                ->required(),
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
                TextColumn::make('title')
                    ->label(__('admin.common.title'))
                    ->state(fn (Perk $record) => Localized::value($record->title))
                    ->description(fn (Perk $record) => Localized::value($record->text)),
                TextColumn::make('icon')
                    ->label(__('admin.common.icon'))
                    ->formatStateUsing(fn (string $state) => __("admin.icons.$state")),
                ToggleColumn::make('is_active')
                    ->label(__('admin.common.is_active')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePerks::route('/'),
        ];
    }
}
