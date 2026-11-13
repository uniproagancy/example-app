<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class Seo
{
    public const DESCRIPTION_LENGTH = 160;

    public const PRIVATE_ROUTES = ['checkout', 'account', 'order', 'password.reset'];

    public static function set(array $data): void
    {
        request()->attributes->set('seo', array_merge(static::data(), $data));
    }

    public static function data(): array
    {
        return (array) request()->attributes->get('seo', []);
    }

    public static function text(?string ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $text = Str::of(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], ' ', (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', (string) $candidate))), ENT_QUOTES | ENT_HTML5))->squish();

            if ($text->isNotEmpty()) {
                return $text->limit(self::DESCRIPTION_LENGTH, '…')->toString();
            }
        }

        return null;
    }

    public static function defaultDescription(): string
    {
        return static::text(Setting::get('seo_description_'.app()->getLocale()))
            ?? __('seo.default_description', ['site' => Site::name()]);
    }

    public static function description(): string
    {
        return static::data()['description'] ?? static::defaultDescription();
    }

    public static function image(): string
    {
        $shareImage = Setting::get('seo_image');

        return static::absolute(static::data()['image']
            ?? (filled($shareImage) ? Storage::disk('public')->url($shareImage) : Site::logoUrl()));
    }

    public static function canonical(): string
    {
        return static::data()['canonical'] ?? url()->current();
    }

    public static function isIndexable(): bool
    {
        if (static::data()['noindex'] ?? false) {
            return false;
        }

        if (request()->routeIs(...self::PRIVATE_ROUTES)) {
            return false;
        }

        return blank(request()->query('q'));
    }

    public static function alternates(): array
    {
        $shared = (array) (view()->shared('alternateUrls') ?? []);

        return collect(Locales::codes())
            ->mapWithKeys(fn (string $locale) => [
                $locale => $shared[$locale] ?? LaravelLocalization::getLocalizedURL($locale, url()->current(), [], true),
            ])
            ->filter()
            ->all();
    }

    public static function absolute(string $url): string
    {
        return Str::startsWith($url, ['http://', 'https://']) ? $url : url($url);
    }

    public static function organization(): array
    {
        return array_filter([
            '@type' => 'Organization',
            '@id' => url('/').'#organization',
            'name' => Site::name(),
            'url' => route('home'),
            'logo' => static::absolute(Site::logoUrl()),
            'email' => Site::email(),
            'telephone' => Site::phone(),
            'sameAs' => array_values(Site::socials()) ?: null,
            'contactPoint' => Site::phone() ? [
                '@type' => 'ContactPoint',
                'telephone' => Site::phone(),
                'contactType' => 'customer service',
                'areaServed' => 'GE',
                'availableLanguage' => ['ka', 'en'],
            ] : null,
        ]);
    }

    public static function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => url('/').'#website',
            'name' => Site::name(),
            'url' => route('home'),
            'inLanguage' => app()->getLocale(),
            'publisher' => ['@id' => url('/').'#organization'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => route('catalog').'?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    public static function product(array $product): array
    {
        return array_filter([
            '@type' => 'Product',
            '@id' => $product['url'].'#product',
            'name' => Catalog::productTitle($product['brand'] ?? null, $product['name'] ?? null),
            'sku' => $product['sku'] ?? null,
            'image' => array_map(static::absolute(...), $product['images'] ?? []) ?: null,
            'description' => static::text($product['description'] ?? null, $product['subtitle'] ?? null),
            'category' => $product['subLabel'] ?? null,
            'brand' => filled($product['brand'] ?? null) ? ['@type' => 'Brand', 'name' => $product['brand']] : null,
            'offers' => [
                '@type' => 'Offer',
                'url' => $product['url'],
                'priceCurrency' => 'GEL',
                'price' => number_format((float) $product['price'], 2, '.', ''),
                'availability' => ($product['inStock'] ?? false) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => ['@id' => url('/').'#organization'],
            ],
        ]);
    }

    public static function breadcrumbs(array $items): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect([['name' => Site::name(), 'url' => route('home')], ...$items])
                ->values()
                ->map(fn (array $item, int $index) => array_filter([
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                    'item' => $item['url'] ?? null,
                ]))
                ->all(),
        ];
    }

    public static function graph(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => array_values([static::organization(), ...(static::data()['schema'] ?? [])]),
        ];
    }
}
