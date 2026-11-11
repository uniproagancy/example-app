<?php

namespace App\Services\Feeds;

use App\Models\Category;
use App\Models\Product;
use App\Support\Catalog;
use App\Support\CategoryTree;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use XMLWriter;

abstract class ProductFeed
{
    protected array $config;

    protected int $written = 0;

    protected array $skipped = ['price' => 0, 'no_name' => 0, 'no_image' => 0, 'no_category' => 0];

    public function __construct()
    {
        $this->config = config('feeds.'.$this->key());
    }

    abstract protected function key(): string;

    abstract protected function writeItem(XMLWriter $xml, Product $product): void;

    public function generate(): string
    {
        $locale = $this->config['locale'];
        $previous = app()->getLocale();
        app()->setLocale($locale);

        $path = Storage::disk('local')->path($this->config['path']);
        @mkdir(dirname($path), 0755, true);

        $temporary = $path.'.tmp';
        $this->written = 0;
        $this->skipped = array_fill_keys(array_keys($this->skipped), 0);

        try {
            $xml = new XMLWriter;
            $xml->openUri($temporary);
            $xml->setIndent(true);
            $xml->setIndentString('  ');
            $xml->startDocument('1.0', 'UTF-8');
            $xml->startElement('rss');
            $xml->writeAttribute('version', '2.0');
            $xml->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
            $xml->startElement('channel');
            $xml->writeElement('title', $this->config['title']);
            $xml->writeElement('link', config('app.url'));
            $xml->writeElement('description', $this->config['description']);

            $this->query()->chunkById(200, function ($products) use ($xml) {
                foreach ($products as $product) {
                    $this->writeItem($xml, $product);
                }

                gc_collect_cycles();
            });

            $xml->endElement();
            $xml->endElement();
            $xml->endDocument();
            $xml->flush();

            rename($temporary, $path);
        } finally {
            app()->setLocale($previous);
        }

        Log::info($this->key().' feed generated', ['items' => $this->written] + $this->skipped);

        return $path;
    }

    protected function query(): Builder
    {
        $excluded = $this->excludedCategoryIds();

        return Product::query()
            ->where('is_active', true)
            ->whereNotNull('category_id')
            ->whereIn('category_id', CategoryTree::nodes()->keys()->all())
            ->when($excluded, fn (Builder $query) => $query->whereNotIn('category_id', $excluded))
            ->when($this->config['exclude_brands'], fn (Builder $query) => $query
                ->whereDoesntHave('brand', fn (Builder $brand) => $brand->whereIn('slug', $this->config['exclude_brands'])))
            ->withTranslation()
            ->with([
                'images',
                'brand',
                'category' => fn ($query) => $query->withTranslation()->with(['parent' => fn ($parent) => $parent->withTranslation()]),
                'offers.supplier',
            ]);
    }

    protected function excludedCategoryIds(): array
    {
        $ids = array_unique(array_merge(
            $this->config['exclude_categories'],
            Category::query()->where('in_feed', false)->pluck('id')->all(),
        ));

        if (! $ids) {
            return [];
        }

        $children = Category::query()->pluck('parent_id', 'id')->groupBy(fn ($parent) => (int) $parent, true);
        $all = [];
        $queue = array_map('intval', $ids);

        while ($queue) {
            $id = array_shift($queue);

            if (isset($all[$id])) {
                continue;
            }

            $all[$id] = true;

            foreach (array_keys($children->get($id, collect())->all()) as $child) {
                $queue[] = (int) $child;
            }
        }

        return array_keys($all);
    }

    protected function prices(Product $product): array
    {
        $shelf = (float) $product->price;
        $selling = (float) (Catalog::find($product->id)['price'] ?? $shelf);

        $was = $selling < $shelf
            ? $shelf
            : ($product->old_price && (float) $product->old_price > $selling ? (float) $product->old_price : null);

        return [$selling, $was];
    }

    protected function skip(Product $product, string $name, float $price, ?string $image): bool
    {
        if ($name === '') {
            $this->skipped['no_name']++;

            return true;
        }

        if ($price < $this->config['min_price']) {
            $this->skipped['price']++;

            return true;
        }

        if (! $image) {
            $this->skipped['no_image']++;

            return true;
        }

        return false;
    }

    protected function money(float $amount): string
    {
        return number_format(round($amount), 2, '.', '').' '.$this->config['currency'];
    }

    protected function description(Product $product, string $name): string
    {
        $text = trim(strip_tags((string) ($product->subtitle ?: $product->description)));

        return $this->clean($text !== '' ? $text : $name, 5000);
    }

    protected function image(Product $product): ?string
    {
        $first = $product->images->sortBy('position')->first();

        return $first ? $this->imageUrl($first->path) : null;
    }

    protected function gallery(Product $product): array
    {
        return $product->images->sortBy('position')->skip(1)->take(10)
            ->map(fn ($image) => $this->imageUrl($image->path))
            ->filter()
            ->values()
            ->all();
    }

    protected function imageUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $url = Storage::disk('public')->url($path);

        return str_starts_with($url, 'http') ? $url : url($url);
    }

    protected function productUrl(Product $product): string
    {
        return LaravelLocalization::getLocalizedURL($this->config['locale'], '/product/'.$product->slug, [], true);
    }

    protected function categoryName(?Category $category): ?string
    {
        $title = trim((string) $category?->title);

        return $title !== '' ? $title : null;
    }

    protected function productType(Category $category): string
    {
        return CategoryTree::ancestors($category->id)
            ->pluck('title')
            ->push($this->categoryName($category))
            ->filter()
            ->implode(' > ');
    }

    protected function priceBand(float $price): string
    {
        return match (true) {
            $price < 200 => '0-200',
            $price < 500 => '200-500',
            $price < 1000 => '500-1000',
            $price < 2500 => '1000-2500',
            default => '2500+',
        };
    }

    protected function monthlyFrom(float $price): string
    {
        $monthly = (int) ceil($price / $this->config['instalment_months']);

        return __('product.quick_monthly_from', ['amount' => Catalog::money($monthly)]);
    }

    protected function clean(string $value, int $limit): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', strip_tags($value));

        return mb_substr(trim($value), 0, $limit);
    }
}
