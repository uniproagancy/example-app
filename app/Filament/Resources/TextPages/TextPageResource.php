<?php

namespace App\Filament\Resources\TextPages;

use App\Filament\Resources\TextPages\Pages\CreateTextPage;
use App\Filament\Resources\TextPages\Pages\EditTextPage;
use App\Filament\Resources\TextPages\Pages\ListTextPages;
use App\Filament\Support\AdminOnly;
use App\Filament\Support\HasAdminLabels;
use App\Filament\Support\Localized;
use App\Models\Page;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TextPageResource extends Resource
{
    use AdminOnly;
    use HasAdminLabels;

    protected static string $labelKey = 'text_pages';

    protected static string $groupKey = 'content';

    protected static ?string $model = Page::class;

    protected static ?string $slug = 'pages';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 3;

    public static function getRecordTitle(?Model $record): string
    {
        return $record?->kaTranslation?->title ?? (string) $record?->slug;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(__('admin.common.content'))
                    ->schema([
                        Localized::relationTabs('page-translations', 'title', fn (string $locale, bool $isPrimary) => [
                            TextInput::make('title')
                                ->label(__('admin.text_pages.title'))
                                ->required($isPrimary)
                                ->maxLength(255),
                            Textarea::make('intro')
                                ->label(__('admin.text_pages.intro'))
                                ->helperText(__('admin.text_pages.intro_help'))
                                ->rows(2)
                                ->maxLength(1000),
                            RichEditor::make('body')
                                ->label(__('admin.text_pages.body'))
                                ->toolbarButtons([
                                    ['bold', 'italic', 'underline', 'link'],
                                    ['h2', 'h3'],
                                    ['bulletList', 'orderedList', 'blockquote'],
                                    ['table'],
                                    ['undo', 'redo'],
                                ]),
                            Textarea::make('meta_description')
                                ->label(__('admin.text_pages.meta_description'))
                                ->helperText(__('admin.text_pages.meta_description_help'))
                                ->rows(2)
                                ->maxLength(300),
                        ]),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make([
                    Section::make(__('admin.common.settings'))
                        ->schema([
                            TextInput::make('slug')
                                ->label(__('admin.text_pages.slug'))
                                ->helperText(__('admin.text_pages.slug_help'))
                                ->required()
                                ->alphaDash()
                                ->maxLength(80)
                                ->unique(ignoreRecord: true),
                            Toggle::make('is_active')
                                ->label(__('admin.common.is_active'))
                                ->default(true),
                            Toggle::make('in_footer')
                                ->label(__('admin.text_pages.in_footer'))
                                ->default(true),
                            TextInput::make('position')
                                ->label(__('admin.common.position'))
                                ->integer()
                                ->default(0),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->reorderable('position')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('kaTranslation'))
            ->columns([
                TextColumn::make('kaTranslation.title')
                    ->label(__('admin.text_pages.title'))
                    ->description(fn (Page $record): string => '/info/'.$record->slug)
                    ->weight('bold')
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('translations', fn (Builder $query) => $query->where('title', 'like', "%{$search}%"))),
                ToggleColumn::make('in_footer')
                    ->label(__('admin.text_pages.in_footer')),
                ToggleColumn::make('is_active')
                    ->label(__('admin.common.is_active')),
                TextColumn::make('updated_at')
                    ->label(__('admin.common.updated_at'))
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('')
                    ->tooltip(__('admin.common.open_on_site'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (Page $record): string => url('ka/info/'.$record->slug), shouldOpenInNewTab: true)
                    ->visible(fn (Page $record): bool => $record->is_active),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTextPages::route('/'),
            'create' => CreateTextPage::route('/create'),
            'edit' => EditTextPage::route('/{record}/edit'),
        ];
    }
}
