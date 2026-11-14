<?php

namespace App\Filament\Resources\Redirects;

use App\Filament\Resources\Redirects\Pages\ManageRedirects;
use App\Filament\Support\AdminOnly;
use App\Filament\Support\HasAdminLabels;
use App\Models\Redirect;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RedirectResource extends Resource
{
    use AdminOnly;
    use HasAdminLabels;

    protected static string $labelKey = 'redirects';

    protected static string $groupKey = 'system';

    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('from_path')
                    ->label(__('admin.redirects.from_path'))
                    ->helperText(__('admin.redirects.from_path_help'))
                    ->placeholder('/ka/product/old-name')
                    ->required()
                    ->maxLength(500)
                    ->dehydrateStateUsing(fn (string $state) => Redirect::normalize($state))
                    ->unique(ignoreRecord: true)
                    ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                        if (in_array(Redirect::normalize($value), ['/', '/ka', '/en', '/admin'], true)) {
                            $fail(__('admin.redirects.protected_path'));
                        }
                    }),
                TextInput::make('to_url')
                    ->label(__('admin.redirects.to_url'))
                    ->helperText(__('admin.redirects.to_url_help'))
                    ->placeholder('/ka/product/new-name')
                    ->required()
                    ->maxLength(1000)
                    ->different('from_path'),
                Select::make('status_code')
                    ->label(__('admin.redirects.status_code'))
                    ->options([
                        301 => __('admin.redirects.codes.301'),
                        302 => __('admin.redirects.codes.302'),
                    ])
                    ->default(301)
                    ->required(),
                Toggle::make('is_active')
                    ->label(__('admin.common.is_active'))
                    ->default(true)
                    ->inline(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('from_path')
                    ->label(__('admin.redirects.from_path'))
                    ->searchable()
                    ->copyable()
                    ->wrap(),
                TextColumn::make('to_url')
                    ->label(__('admin.redirects.to_url'))
                    ->searchable()
                    ->url(fn (Redirect $record) => $record->target(), shouldOpenInNewTab: true)
                    ->wrap(),
                TextColumn::make('status_code')
                    ->label(__('admin.redirects.status_code'))
                    ->badge()
                    ->alignCenter(),
                IconColumn::make('is_automatic')
                    ->label(__('admin.redirects.is_automatic'))
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('hits')
                    ->label(__('admin.redirects.hits'))
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('last_hit_at')
                    ->label(__('admin.redirects.last_hit_at'))
                    ->since()
                    ->sortable()
                    ->placeholder(__('admin.common.none')),
                ToggleColumn::make('is_active')
                    ->label(__('admin.common.is_active')),
            ])
            ->filters([
                TernaryFilter::make('is_automatic')
                    ->label(__('admin.redirects.is_automatic')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRedirects::route('/'),
        ];
    }
}
