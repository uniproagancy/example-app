<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\Site;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManageSettings extends Page
{
    public const KEYS = ['site_name', 'phone', 'hotline', 'email', 'hours_week', 'hours_sunday', ...Site::SOCIAL_NETWORKS];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'settings';

    public ?array $data = [];

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.system');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.settings.label');
    }

    public function getTitle(): string
    {
        return __('admin.settings.label');
    }

    public function mount(): void
    {
        $this->form->fill(collect(self::KEYS)->mapWithKeys(fn (string $key) => [$key => Setting::get($key)])->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(2)
            ->components([
                Section::make(__('admin.settings.contacts'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('site_name')
                            ->label(__('admin.settings.site_name'))
                            ->required()
                            ->maxLength(60),
                        TextInput::make('email')
                            ->label(__('admin.settings.email'))
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label(__('admin.settings.phone'))
                            ->tel()
                            ->maxLength(32),
                        TextInput::make('hotline')
                            ->label(__('admin.settings.hotline'))
                            ->maxLength(32),
                    ])
                    ->columnSpanFull(),
                Section::make(__('admin.settings.hours'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('hours_week')
                            ->label(__('admin.settings.hours_week'))
                            ->placeholder('10:00–20:00')
                            ->maxLength(40),
                        TextInput::make('hours_sunday')
                            ->label(__('admin.settings.hours_sunday'))
                            ->placeholder('11:00–18:00')
                            ->maxLength(40),
                    ])
                    ->columnSpanFull(),
                Section::make(__('admin.settings.socials'))
                    ->description(__('admin.settings.socials_help'))
                    ->columns(2)
                    ->schema(collect(Site::SOCIAL_NETWORKS)->map(fn (string $network) => TextInput::make($network)
                        ->label(__("admin.settings.networks.$network"))
                        ->url()
                        ->startsWith(['https://'])
                        ->placeholder("https://www.$network.com/elio.ge")
                        ->prefixIcon(Heroicon::OutlinedLink)
                        ->maxLength(255))->all())
                    ->columnSpanFull(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label(__('admin.settings.save'))
                            ->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            if (in_array($key, self::KEYS, true)) {
                Setting::put($key, filled($value) ? (string) $value : null);
            }
        }

        Notification::make()->title(__('admin.settings.saved'))->success()->send();
    }
}
