<?php

namespace App\Filament\Resources\Brands;

use App\Filament\Resources\Brands\Pages\ManageBrands;
use App\Filament\Support\HasAdminLabels;
use App\Models\Brand;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class BrandResource extends Resource
{
    use HasAdminLabels;

    protected static string $labelKey = 'brands';

    protected static string $groupKey = 'catalog';

    protected static ?string $model = Brand::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label(__('admin.brands.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label(__('admin.common.slug'))
                    ->helperText(__('admin.common.slug_help'))
                    ->maxLength(255)
                    ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Str::slug($state) : null)
                    ->unique(ignoreRecord: true),
                Toggle::make('show_on_home')
                    ->label(__('admin.brands.show_on_home'))
                    ->helperText(__('admin.brands.show_on_home_help', ['count' => Brand::HOME_LIMIT])),
                TextInput::make('home_position')
                    ->label(__('admin.brands.home_position'))
                    ->integer()
                    ->default(0),
                Toggle::make('is_active')
                    ->label(__('admin.common.is_active'))
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('home_position')
            ->reorderable('home_position')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('products'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.brands.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('products_count')
                    ->label(__('admin.brands.products'))
                    ->alignCenter()
                    ->sortable(),
                ToggleColumn::make('show_on_home')
                    ->label(__('admin.brands.show_on_home')),
                TextColumn::make('home_position')
                    ->label(__('admin.brands.home_position'))
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label(__('admin.common.is_active')),
            ])
            ->filters([
                TernaryFilter::make('show_on_home')
                    ->label(__('admin.brands.show_on_home')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBrands::route('/'),
        ];
    }
}
