<?php

namespace App\Providers;

use App\Http\Middleware\CacheGuestPages;
use App\Jobs\ImportProductJob;
use App\Models\Supplier;
use App\Services\Facebook\Pixel;
use App\Support\ServedVite;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Illuminate\Foundation\Vite;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Mcamara\LaravelLocalization\Traits\LoadsTranslatedCachedRoutes;

class AppServiceProvider extends ServiceProvider
{
    use LoadsTranslatedCachedRoutes;

    public function register(): void
    {
        $this->app->singleton(Pixel::class);
        $this->app->singleton(Vite::class, ServedVite::class);
    }

    public function boot(): void
    {
        Model::preventLazyLoading($this->app->environment('local', 'testing'));

        DB::listen(fn (QueryExecuted $query) => CacheGuestPages::noticeQuery($query->sql));
        Event::listen(JobProcessed::class, fn () => CacheGuestPages::flushIfDirty());
        $this->app->terminating(fn () => CacheGuestPages::flushIfDirty());

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
