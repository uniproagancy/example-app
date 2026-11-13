<?php

namespace App\Providers\Filament;

use App\Http\Middleware\SetAdminLocale;
use App\Support\Site;
use Filament\Enums\ThemeMode;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('ELIO.GE')
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2.25rem')
            ->favicon(fn () => Site::faviconUrl())
            ->colors([
                'primary' => '#0B63F6',
                'gray' => Color::Slate,
                'info' => '#14B4FF',
                'danger' => '#E5484D',
                'success' => '#14804A',
                'warning' => '#F5A524',
            ])
            ->font('HelveticaRegular', provider: LocalFontProvider::class)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->defaultThemeMode(ThemeMode::Light)
            ->topNavigation()
            ->maxContentWidth(Width::ScreenTwoExtraLarge)
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => Blade::render('@fonts'))
            ->spa()
            ->navigationGroups([
                NavigationGroup::make(fn () => __('admin.groups.sales')),
                NavigationGroup::make(fn () => __('admin.groups.catalog')),
                NavigationGroup::make(fn () => __('admin.groups.home')),
                NavigationGroup::make(fn () => __('admin.groups.content')),
                NavigationGroup::make(fn () => __('admin.groups.import')),
                NavigationGroup::make(fn () => __('admin.groups.system')),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->middleware([
                SetAdminLocale::class,
            ], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
