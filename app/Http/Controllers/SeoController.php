<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\Catalog;
use App\Support\CategoryTree;
use App\Support\Locales;
use App\Support\Seo;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use XMLWriter;

class SeoController extends Controller
{
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /payment/',
            'Disallow: /feed/',
            ...collect(Locales::codes())->flatMap(fn (string $locale) => [
                "Disallow: /{$locale}/checkout",
                "Disallow: /{$locale}/account",
                "Disallow: /{$locale}/order/",
                "Disallow: /{$locale}/reset-password/",
            ])->all(),
            'Allow: /',
            '',
            'Sitemap: '.route('seo.sitemap'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function sitemap(): Response
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->writeAttribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');
        $xml->writeAttribute('xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');

        foreach ($this->entries() as $entry) {
            $this->writeEntry($xml, $entry);
        }

        $xml->endElement();
        $xml->endDocument();

        return response($xml->outputMemory(), 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    protected function entries(): Collection
    {
        $static = collect(['/', '/catalog', '/about', '/contact'])
            ->map(fn (string $path) => ['alternates' => $this->localized(fn () => $path), 'images' => []]);

        $pages = Page::query()->active()->pluck('slug')
            ->map(fn (string $slug) => ['alternates' => $this->localized(fn () => '/info/'.$slug), 'images' => []]);

        $categories = CategoryTree::nodes()
            ->filter(fn (array $node) => Catalog::productsIn($node)->isNotEmpty())
            ->map(fn (array $node) => [
                'alternates' => $this->localized(fn (string $locale) => isset($node['slugs'][$locale]) ? '/categories/'.$node['slugs'][$locale] : null),
                'images' => [],
            ]);

        $products = Catalog::products()
            ->map(fn (array $product) => [
                'alternates' => $this->localized(fn (string $locale) => isset($product['slugs'][$locale]) ? '/product/'.$product['slugs'][$locale] : null),
                'images' => array_slice(array_map(Seo::absolute(...), $product['images']), 0, 5),
            ]);

        return $static->concat($pages)->concat($categories)->concat($products)
            ->filter(fn (array $entry) => $entry['alternates'] !== []);
    }

    protected function localized(callable $path): array
    {
        return collect(Locales::codes())
            ->mapWithKeys(function (string $locale) use ($path) {
                $target = $path($locale);

                return [$locale => $target === null ? null : LaravelLocalization::getLocalizedURL($locale, $target, [], true)];
            })
            ->filter()
            ->all();
    }

    protected function writeEntry(XMLWriter $xml, array $entry): void
    {
        foreach ($entry['alternates'] as $url) {
            $xml->startElement('url');
            $xml->writeElement('loc', $url);

            if (count($entry['alternates']) > 1) {
                foreach ($entry['alternates'] as $locale => $alternate) {
                    $this->writeAlternate($xml, $locale, $alternate);
                }

                if (isset($entry['alternates'][Locales::default()])) {
                    $this->writeAlternate($xml, 'x-default', $entry['alternates'][Locales::default()]);
                }
            }

            foreach ($entry['images'] as $image) {
                $xml->startElement('image:image');
                $xml->writeElement('image:loc', $image);
                $xml->endElement();
            }

            $xml->endElement();
        }
    }

    protected function writeAlternate(XMLWriter $xml, string $locale, string $url): void
    {
        $xml->startElement('xhtml:link');
        $xml->writeAttribute('rel', 'alternate');
        $xml->writeAttribute('hreflang', $locale);
        $xml->writeAttribute('href', $url);
        $xml->endElement();
    }
}
