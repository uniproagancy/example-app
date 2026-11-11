<?php

namespace App\Providers;

use App\Jobs\ImportProductJob;
use App\Models\Supplier;
use App\Services\Facebook\Pixel;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Mcamara\LaravelLocalization\Traits\LoadsTranslatedCachedRoutes;

class AppServiceProvider extends ServiceProvider
{
    use LoadsTranslatedCachedRoutes;

    public function register(): void
    {
        $this->app->singleton(Pixel::class);
    }

    public function boot(): void
    {
        RouteServiceProvider::loadCachedRoutesUsing(fn () => $this->loadCachedRoutes());

        RateLimiter::for('import', function (ImportProductJob $job) {
            static $rates = [];

            $rates[$job->supplierId] ??= (int) (Supplier::query()->find($job->supplierId)?->config['rate'] ?? config('import.rate', 120));

            return Limit::perMinute(max(1, $rates[$job->supplierId]))->by('import:'.$job->supplierId);
        });

        Table::configureUsing(fn (Table $table) => $table
            ->filtersLayout(FiltersLayout::BeforeContent)
            ->deferFilters(false));
    }
}
