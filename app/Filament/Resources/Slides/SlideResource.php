<?php

namespace App\Filament\Resources\Slides;

use App\Filament\Resources\Slides\Pages\ManageSlides;
use App\Filament\Support\CategoryOptions;
use App\Filament\Support\HasAdminLabels;
use App\Filament\Support\Localized;
use App\Filament\Support\Schedule;
use App\Models\Category;
use App\Models\Slide;
use App\Support\WebpImage;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class SlideResource extends Resource
{
    use HasAdminLabels;

    protected static string $labelKey = 'slides';

    protected static string $groupKey = 'home';

    protected static ?string $model = Slide::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Localized::tabs('slide-content', fn (string $locale, bool $isPrimary) => [
                    TextInput::make("title.$locale")
                        ->label(__('admin.common.title'))
                        ->required($isPrimary)
                        ->maxLength(255),
                    Textarea::make("text.$locale")
                        ->label(__('admin.common.text'))
                        ->rows(2),
                    TextInput::make("button_label.$locale")
                        ->label(__('admin.slides.button_label'))
                        ->maxLength(60),
                ]),
                FileUpload::make('image')
                    ->label(__('admin.common.image'))
                    ->helperText(__('admin.slides.image_help'))
                    ->image()
                    ->disk('public')
                    ->directory('slides')
                    ->visibility('public')
                    ->saveUploadedFileUsing(WebpImage::uploader())
                    ->maxSize(5120)
                    ->imageEditor()
                    ->columnSpanFull(),
                Select::make('category_id')
                    ->label(__('admin.common.category'))
                    ->options(fn () => CategoryOptions::paths())
                    ->searchable(),
                TextInput::make('url')
                    ->label(__('admin.common.url'))
                    ->helperText(__('admin.common.url_help'))
                    ->maxLength(255),
                Grid::make(3)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('theme')
                            ->label(__('admin.common.theme'))
                            ->options(Localized::options('admin.themes', Slide::THEMES))
                            ->default('blue')
                            ->required(),
                        Select::make('icon')
                            ->label(__('admin.common.icon'))
                            ->options(Localized::options('admin.icons', Category::ICONS)),
                        TextInput::make('position')
                            ->label(__('admin.common.position'))
                            ->integer()
                            ->default(0),
                    ]),
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
                ImageColumn::make('image')
                    ->label('')
                    ->disk('public')
                    ->imageHeight(44),
                TextColumn::make('title')
                    ->label(__('admin.common.title'))
                    ->state(fn (Slide $record) => Localized::value($record->title))
                    ->description(fn (Slide $record) => Localized::value($record->text))
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
            'index' => ManageSlides::route('/'),
        ];
    }
}
