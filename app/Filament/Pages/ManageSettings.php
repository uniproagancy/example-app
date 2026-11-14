<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminOnlyPage;
use App\Models\Setting;
use App\Services\Orders\OrderNotifier;
use App\Services\Sms\SmsOffice;
use App\Support\Locales;
use App\Support\Site;
use App\Support\WebpImage;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class ManageSettings extends Page
{
    use AdminOnlyPage;

    public const KEYS = ['logo', 'logo_with_name', 'site_name', 'phone', 'hotline', 'email', 'hours_week', 'hours_sunday', ...Site::SOCIAL_NETWORKS, 'seo_image', 'google_site_verification', 'order_alert_emails', 'order_alert_phone', 'notify_buyer_mail', 'notify_buyer_sms', 'ga4_id', 'facebook_pixel_id'];

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
        $this->form->fill([
            ...collect(static::keys())->mapWithKeys(fn (string $key) => [$key => Setting::get($key)])->all(),
            'logo_with_name' => (bool) Setting::get('logo_with_name'),
            'notify_buyer_mail' => OrderNotifier::enabled('notify_buyer_mail'),
            'notify_buyer_sms' => OrderNotifier::enabled('notify_buyer_sms'),
        ]);
    }

    public static function keys(): array
    {
        return [...self::KEYS, ...array_map(fn (string $locale) => 'seo_description_'.$locale, Locales::codes())];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(2)
            ->components([
                Section::make(__('admin.settings.logo_section'))
                    ->description(__('admin.settings.logo_help'))
                    ->columns(2)
                    ->schema([
                        FileUpload::make('logo')
                            ->label(__('admin.settings.logo'))
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->disk('public')
                            ->directory('branding')
                            ->visibility('public')
                            ->saveUploadedFileUsing(WebpImage::uploader())
                            ->maxSize(2048)
                            ->imagePreviewHeight('96'),
                        Toggle::make('logo_with_name')
                            ->label(__('admin.settings.logo_with_name'))
                            ->helperText(__('admin.settings.logo_with_name_help')),
                    ])
                    ->columnSpanFull(),
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
                Section::make(__('admin.settings.order_notifications'))
                    ->description(fn (): string => __('admin.settings.order_notifications_help').' '.(app(SmsOffice::class)->isConfigured()
                        ? __('admin.settings.sms_ready')
                        : __('admin.settings.sms_missing')))
                    ->columns(2)
                    ->schema([
                        Toggle::make('notify_buyer_mail')
                            ->label(__('admin.settings.notify_buyer_mail')),
                        Toggle::make('notify_buyer_sms')
                            ->label(__('admin.settings.notify_buyer_sms')),
                        TextInput::make('order_alert_emails')
                            ->label(__('admin.settings.order_alert_emails'))
                            ->helperText(__('admin.settings.order_alert_emails_help'))
                            ->placeholder('orders@elivo.ge, manager@elivo.ge')
                            ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                                $invalid = collect(preg_split('/[\s,;]+/', (string) $value))
                                    ->filter()
                                    ->reject(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL));

                                if ($invalid->isNotEmpty()) {
                                    $fail(__('admin.settings.order_alert_emails_invalid', ['emails' => $invalid->implode(', ')]));
                                }
                            })
                            ->maxLength(500),
                        TextInput::make('order_alert_phone')
                            ->label(__('admin.settings.order_alert_phone'))
                            ->helperText(__('admin.settings.order_alert_phone_help'))
                            ->tel()
                            ->placeholder('5XX XX XX XX')
                            ->maxLength(32),
                    ])
                    ->columnSpanFull(),
                Section::make(__('admin.settings.analytics'))
                    ->description(__('admin.settings.analytics_help'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('ga4_id')
                            ->label(__('admin.settings.ga4_id'))
                            ->helperText(__('admin.settings.ga4_id_help'))
                            ->placeholder('G-XXXXXXXXXX')
                            ->regex('/^G-[A-Z0-9]{4,20}$/i')
                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? mb_strtoupper(trim($state)) : null)
                            ->maxLength(30),
                        TextInput::make('facebook_pixel_id')
                            ->label(__('admin.settings.facebook_pixel_id'))
                            ->helperText(__('admin.settings.facebook_pixel_id_help'))
                            ->placeholder('123456789012345')
                            ->regex('/^[0-9]{6,20}$/')
                            ->maxLength(20),
                    ])
                    ->columnSpanFull(),
                Section::make(__('admin.settings.seo'))
                    ->description(__('admin.settings.seo_help'))
                    ->columns(2)
                    ->schema([
                        ...collect(Locales::codes())->map(fn (string $locale) => Textarea::make('seo_description_'.$locale)
                            ->label(__('admin.settings.seo_description', ['locale' => strtoupper($locale)]))
                            ->helperText(__('admin.settings.seo_description_help'))
                            ->rows(3)
                            ->maxLength(300))->all(),
                        FileUpload::make('seo_image')
                            ->label(__('admin.settings.seo_image'))
                            ->helperText(__('admin.settings.seo_image_help'))
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->disk('public')
                            ->directory('branding')
                            ->visibility('public')
                            ->maxSize(4096)
                            ->imagePreviewHeight('96'),
                        TextInput::make('google_site_verification')
                            ->label(__('admin.settings.google_site_verification'))
                            ->helperText(__('admin.settings.google_site_verification_help'))
                            ->maxLength(120),
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
        $previous = ['logo' => Setting::get('logo'), 'seo_image' => Setting::get('seo_image')];
        $state = $this->form->getState();

        foreach ($previous as $key => $path) {
            if (filled($path) && $path !== ($state[$key] ?? null)) {
                Storage::disk('public')->delete($path);
            }
        }

        foreach ($state as $key => $value) {
            if (in_array($key, ['notify_buyer_mail', 'notify_buyer_sms'], true)) {
                Setting::put($key, $value ? '1' : '0');
            } elseif (in_array($key, static::keys(), true)) {
                Setting::put($key, filled($value) ? (string) $value : null);
            }
        }

        Notification::make()->title(__('admin.settings.saved'))->success()->send();
    }
}
