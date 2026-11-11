<?php

namespace App\Filament\Resources\Faqs;

use App\Filament\Resources\Faqs\Pages\ManageFaqs;
use App\Filament\Support\HasAdminLabels;
use App\Filament\Support\Localized;
use App\Models\Faq;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class FaqResource extends Resource
{
    use HasAdminLabels;

    protected static string $labelKey = 'faqs';

    protected static string $groupKey = 'content';

    protected static ?string $model = Faq::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Localized::tabs('faq-content', fn (string $locale, bool $isPrimary) => [
                TextInput::make("question.$locale")
                    ->label(__('admin.faqs.question'))
                    ->required($isPrimary)
                    ->maxLength(255),
                Textarea::make("answer.$locale")
                    ->label(__('admin.faqs.answer'))
                    ->required($isPrimary)
                    ->rows(4),
            ]),
            Select::make('icon')
                ->label(__('admin.common.icon'))
                ->options(Localized::options('admin.icons', Faq::ICONS))
                ->default('help')
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
                TextColumn::make('question')
                    ->label(__('admin.faqs.question'))
                    ->state(fn (Faq $record) => Localized::value($record->question))
                    ->description(fn (Faq $record) => str(Localized::value($record->answer))->limit(90))
                    ->wrap(),
                TextColumn::make('icon')
                    ->label(__('admin.common.icon'))
                    ->formatStateUsing(fn (string $state) => __("admin.icons.$state"))
                    ->toggleable(),
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
            'index' => ManageFaqs::route('/'),
        ];
    }
}
