<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Support\AdminOnly;
use App\Filament\Support\HasAdminLabels;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Password;

class UserResource extends Resource
{
    use AdminOnly;
    use HasAdminLabels;

    protected static string $labelKey = 'users';

    protected static string $groupKey = 'system';

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function isCurrentUser(?User $user): bool
    {
        return $user !== null && $user->is(auth()->user());
    }

    public static function saveUser(User $user, array $data): User
    {
        $user->forceFill($data)->save();

        return $user;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label(__('admin.users.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('admin.users.email'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('phone')
                    ->label(__('admin.users.phone'))
                    ->tel()
                    ->maxLength(32),
                TextInput::make('password')
                    ->label(__('admin.users.password'))
                    ->helperText(fn (string $operation) => $operation === 'edit' ? __('admin.users.password_help') : null)
                    ->password()
                    ->revealable()
                    ->rule(Password::defaults())
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state)),
                Toggle::make('is_admin')
                    ->label(__('admin.users.is_admin'))
                    ->helperText(fn (?User $record) => static::isCurrentUser($record) ? __('admin.users.cannot_demote_self') : __('admin.users.is_admin_help'))
                    ->disabled(fn (?User $record) => static::isCurrentUser($record))
                    ->live()
                    ->columnSpanFull(),
                Toggle::make('is_operator')
                    ->label(__('admin.users.is_operator'))
                    ->helperText(__('admin.users.is_operator_help'))
                    ->hidden(fn (Get $get): bool => (bool) $get('is_admin'))
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['orders', 'favorites']))
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.users.name'))
                    ->description(fn (User $record) => $record->email)
                    ->searchable(['name', 'email', 'phone']),
                TextColumn::make('phone')
                    ->label(__('admin.users.phone'))
                    ->placeholder(__('admin.common.none'))
                    ->toggleable(),
                TextColumn::make('orders_count')
                    ->label(__('admin.users.orders'))
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('favorites_count')
                    ->label(__('admin.users.favorites'))
                    ->alignCenter()
                    ->toggleable(),
                IconColumn::make('is_admin')
                    ->label(__('admin.users.is_admin'))
                    ->boolean()
                    ->alignCenter(),
                IconColumn::make('is_operator')
                    ->label(__('admin.users.is_operator'))
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime('d.m.Y')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_admin')
                    ->label(__('admin.users.admins_only')),
                TernaryFilter::make('is_operator')
                    ->label(__('admin.users.is_operator')),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (User $record, array $data) => static::saveUser($record, $data)),
                DeleteAction::make()
                    ->hidden(fn (User $record) => static::isCurrentUser($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
