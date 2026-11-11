<?php

namespace App\Services\Feeds;

use App\Models\Product;
use App\Support\Catalog;
use Illuminate\Database\Eloquent\Builder;
use XMLWriter;

class FacebookFeed extends ProductFeed
{
    protected ?PrimaryFeature $primaryFeature = null;

    protected function query(): Builder
    {
        return parent::query()->with(($this->primaryFeature ??= app(PrimaryFeature::class))->eagerRelations());
    }

    protected function key(): string
    {
        return 'facebook';
    }

    protected function writeItem(XMLWriter $xml, Product $product): void
    {
        $name = trim((string) $product->name);
        [$price, $was] = $this->prices($product);
        $was = $was !== null && round($was) > round($price) ? $was : null;
        $image = $this->image($product);

        if ($this->skip($product, $name, $price, $image)) {
            return;
        }

        $brand = $product->brand?->name ?: '—';
        $category = $product->category;
        $feature = ($this->primaryFeature ??= app(PrimaryFeature::class))->label($product);

        $xml->startElement('item');

        $xml->writeElement('g:id', (string) $product->id);
        $xml->writeElement('g:item_group_id', $this->groupId($product));
        $xml->writeElement('g:title', $this->title($brand, $name));
        $xml->writeElement('g:description', $this->description($product, $name));
        $xml->writeElement('g:short_description', $feature);
        $xml->writeElement('g:link', $this->link($product));
        $xml->writeElement('g:image_link', $image);

        foreach ($this->gallery($product) as $extra) {
            $xml->writeElement('g:additional_image_link', $extra);
        }

        $xml->writeElement('g:availability', $this->availability($product));
        $xml->writeElement('g:condition', 'new');
        $xml->writeElement('g:brand', $this->clean($brand, 70));

        $xml->writeElement('g:price', $this->money($was ?: $price));

        if ($was) {
            $xml->writeElement('g:sale_price', $this->money($price));
        }

        if ($google = $category?->google_category_id ?? $category?->parent?->google_category_id) {
            $xml->writeElement('g:google_product_category', (string) $google);
        }

        if ($categoryName = $this->categoryName($category)) {
            $xml->writeElement('g:product_type', $this->clean($categoryName, 750));
        }

        $xml->writeElement('g:quantity_to_sell_on_facebook', (string) max(1, (int) $product->stock));
        $xml->writeElement('g:shipping_weight', $this->weight($product));

        $xml->writeElement('g:custom_label_0', $this->instalmentLabel($product, $price));
        $xml->writeElement('g:custom_label_1', $feature);

        if ($parent = $this->categoryName($category?->parent) ?? $this->categoryName($category)) {
            $xml->writeElement('g:custom_label_2', $this->clean($parent, 100));
        }

        if ($supplier = $this->supplier($product)) {
            $xml->writeElement('g:custom_label_3', $this->clean($supplier, 100));
        }

        $xml->writeElement('g:custom_label_4', $was !== null ? Catalog::money($was) : '');

        $xml->endElement();
        $xml->flush();

        $this->written++;
    }

    protected function title(string $brand, string $name): string
    {
        return $this->clean(Catalog::productTitle($brand === '—' ? null : $brand, $name), 150);
    }

    protected function groupId(Product $product): string
    {
        return $product->variant_group
            ? 'group_'.$product->variant_group
            : 'product_default_group'.$product->id;
    }

    protected function link(Product $product): string
    {
        $url = $this->productUrl($product);

        return $this->config['utm']
            ? $url.'?'.$this->config['utm']
            : $url;
    }

    protected function instalmentLabel(Product $product, float $price): string
    {
        if ($product->is_preorder) {
            return 'preorder';
        }

        if ($price <= $this->config['instalment_from']) {
            return '';
        }

        return $this->monthlyFrom($price);
    }

    protected function supplier(Product $product): ?string
    {
        return $product->offers
            ->sortBy(fn ($offer) => $offer->supplier?->priority ?? PHP_INT_MAX)
            ->first()?->supplier?->name;
    }

    protected function weight(Product $product): string
    {
        return number_format((float) $product->weight / 1000, 2, '.', '').' kg';
    }

    protected function availability(Product $product): string
    {
        return match (true) {
            $product->stock > 0 => 'in stock',
            (bool) $product->is_preorder => 'available for order',
            default => 'out of stock',
        };
    }
}
