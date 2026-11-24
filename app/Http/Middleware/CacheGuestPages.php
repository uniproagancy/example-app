<?php

namespace App\Http\Middleware;

use App\Models\AbandonedCart;
use App\Models\ActivityLog;
use App\Models\Address;
use App\Models\ContactMessage;
use App\Models\Favorite;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderItem;
use App\Models\OrderPixelData;
use App\Models\PaymentCallback;
use App\Models\PaymentTransaction;
use App\Models\PriceAlert;
use App\Models\PromoCode;
use App\Models\User;
use App\Support\Cart;
use App\Support\RecentlyViewed;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheGuestPages
{
    public const STORE = 'pages';

    public const MINUTES = 5;

    public const VERSION_KEY = 'page-cache.version';

    public const CSRF = '__ELIO_PAGE_CSRF__';

    public const FRESH = '/<!--fresh:([a-z0-9._-]+)-->.*?<!--\/fresh-->/s';

    public const FRESH_VIEWS = ['partials.pixel', 'partials.cookie-consent'];

    public const VISITOR_MODELS = [
        AbandonedCart::class, ActivityLog::class, Address::class, ContactMessage::class, Favorite::class, Invoice::class,
        Order::class, OrderEvent::class, OrderItem::class, OrderPixelData::class, PaymentCallback::class,
        PaymentTransaction::class, PriceAlert::class, PromoCode::class, User::class,
    ];

    protected static bool $dirty = false;

    public static function flush(): void
    {
        static::$dirty = true;
    }

    public static function flushIfDirty(): void
    {
        if (! static::$dirty) {
            return;
        }

        static::$dirty = false;
        Cache::forever(self::VERSION_KEY, (int) Cache::get(self::VERSION_KEY, 0) + 1);
    }

    public const SERVICE_TABLES = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens', 'migrations'];

    public const WRITE = '/^\s*(?:insert\s+(?:or\s+\w+\s+)?into|replace\s+into|update|delete\s+from)\s+[`"\[]?([a-z0-9_]+)/i';

    /**
     * @var array<string, true>|null
     */
    protected static ?array $ignoredTables = null;

    public static function noticeQuery(string $sql): void
    {
        if (static::$dirty || ! preg_match(self::WRITE, $sql, $match)) {
            return;
        }

        static::$ignoredTables ??= array_fill_keys([
            ...self::SERVICE_TABLES,
            ...array_map(fn (string $model) => (new $model)->getTable(), self::VISITOR_MODELS),
        ], true);

        if (! isset(static::$ignoredTables[strtolower($match[1])])) {
            static::flush();
        }
    }

    public static function fresh(string $view): string
    {
        return '<!--fresh:'.$view.'-->'.view($view)->render().'<!--/fresh-->';
    }

    public function handle(Request $request, Closure $next): Response
    {
        static::flushIfDirty();

        if (! $this->cacheable($request)) {
            return $next($request);
        }

        $key = $this->key($request);
        $cached = Cache::store(self::STORE)->get($key);

        if (is_string($cached) && ($html = @gzuncompress($cached)) !== false) {
            return response($this->serve($html), 200, ['Content-Type' => 'text/html; charset=UTF-8', 'X-Page-Cache' => 'hit']);
        }

        $response = $next($request);

        if ($this->storable($response)) {
            Cache::store(self::STORE)->put($key, gzcompress($this->strip((string) $response->getContent()), 6), now()->addMinutes(self::MINUTES));
            $response->headers->set('X-Page-Cache', 'miss');
        }

        return $response;
    }

    protected function cacheable(Request $request): bool
    {
        return config('cache.page_cache', true)
            && $request->isMethod('GET')
            && $request->getQueryString() === null
            && ! $request->header('X-Livewire')
            && ! $request->user()
            && Cart::items() === []
            && RecentlyViewed::ids() === []
            && session()->get('_flash.old', []) === []
            && session()->get('pixel_events', []) === []
            && ! session()->has('errors');
    }

    protected function storable(Response $response): bool
    {
        return $response->getStatusCode() === 200
            && str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')
            && ! $response->headers->has('Location')
            && session()->get('_flash.new', []) === []
            && session()->get('pixel_events', []) === []
            && Cart::items() === []
            && RecentlyViewed::ids() === []
            && str_contains((string) $response->getContent(), '</html>');
    }

    protected function key(Request $request): string
    {
        $build = @filemtime(public_path('build/manifest.json')) ?: 0;

        return 'page:'.md5(app()->getLocale().'|'.$request->url().'|'.$build.'|'.Cache::get(self::VERSION_KEY, 0));
    }

    protected function strip(string $html): string
    {
        $html = (string) preg_replace(self::FRESH, '<!--fresh:$1--><!--/fresh-->', $html);

        return str_replace(csrf_token(), self::CSRF, $html);
    }

    protected function serve(string $html): string
    {
        $html = (string) preg_replace_callback(self::FRESH, fn (array $match) => in_array($match[1], self::FRESH_VIEWS, true) ? static::fresh($match[1]) : '', $html);

        return str_replace(self::CSRF, csrf_token(), $html);
    }
}
