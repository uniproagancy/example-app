<?php

namespace App\Filament\Resources\HomeBlocks;

use App\Filament\Resources\HomeBlocks\Pages\ManageHomeBlocks;
use App\Filament\Support\AdminOnly;
use App\Filament\Support\HasAdminLabels;
use App\Filament\Support\Localized;
use App\Models\HomeBlock;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class HomeBlockResource extends Resource
{
    use AdminOnly;
    use HasAdminLabels;

    protected static string $labelKey = 'home_blocks';

    protected static string $groupKey = 'content';

    protected static ?string $model = HomeBlock::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?int $navigationSort = 0;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Localized::tabs('home-block', fn (string $locale) => [
                TextInput::make("kicker.$locale")
                    ->label(__('admin.home_blocks.kicker'))
                    ->maxLength(120),
                TextInput::make("title.$locale")
                    ->label(__('admin.common.title'))
                    ->maxLength(255),
            ]),
            Toggle::make('is_active')
                ->label(__('admin.common.is_active')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description(__('admin.home_blocks.help'))
            ->defaultSort('position')
            ->reorderable('position')
            ->paginated(false)
            ->columns([
                TextColumn::make('key')
                    ->label(__('admin.home_blocks.key'))
                    ->formatStateUsing(fn (string $state) => __("admin.home_blocks.keys.$state"))
                    ->weight('bold'),
                TextColumn::make('title')
                    ->label(__('admin.common.title'))
                    ->state(fn (HomeBlock $record) => Localized::value($record->title))
                    ->placeholder(__('admin.common.none')),
                ToggleColumn::make('is_active')
                    ->label(__('admin.common.is_active')),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageHomeBlocks::route('/'),
        ];
    }
}
