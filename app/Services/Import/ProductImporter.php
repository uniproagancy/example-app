<?php

namespace App\Services\Import;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\ProductSpec;
use App\Models\ProductSpecTranslation;
use App\Models\Supplier;
use App\Services\Feeds\PrimaryFeatureFormatter;
use App\Services\Import\Drivers\Alta\AltaDriver;
use App\Support\Catalog;
use App\Support\Locales;
use App\Support\SsdCapacity;
use App\Support\Text;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductImporter
{
    public function __construct(
        protected TaxonomyResolver $taxonomy,
        protected ImageDownloader $images,
    ) {}

    public function import(Supplier $supplier, ProductPayload $payload, bool $insist = false): ?Product
    {
        $payload = $this->repair($payload);

        [$product, $isNew] = $this->write($supplier, $payload, $insist);

        $wanted = min(count($payload->images), ImageDownloader::MAX_IMAGES);

        if ($product && $wanted > 0 && ($supplier->driver === AltaDriver::class || $isNew || $product->images()->count() < $wanted)) {
            $this->images->sync($product, $payload->images);
        }

        if ($product && $isNew && ! $this->keep($supplier, $product)) {
            return null;
        }

        if ($product && $isNew) {
            $this->publish($supplier, $product);
        }

        return $product;
    }

    protected function publish(Supplier $supplier, Product $product): void
    {
        $wanted = $supplier->config['publish_on_import'] ?? config('import.publish_on_import', false);

        if (! $wanted || $product->is_active) {
            return;
        }

        if (! Product::readyToPublish()->whereKey($product->id)->exists()) {
            return;
        }

        $product->update(['is_active' => true]);

        $this->log('published on arrival', $supplier, null, [
            'product' => $product->id,
            'sku' => $product->sku,
        ]);
    }

    protected function repair(ProductPayload $payload): ProductPayload
    {
        return new ProductPayload(
            externalId: $payload->externalId,
            sku: $payload->sku,
            costPrice: $payload->costPrice,
            oldCostPrice: $payload->oldCostPrice,
            stock: $payload->stock,
            brandName: Text::utf8($payload->brandName),
            categoryName: Text::utf8($payload->categoryName),
            translations: Text::utf8All($payload->translations),
            specs: Text::utf8All($payload->specs),
            images: $payload->images,
            weight: $payload->weight,
            variantGroup: $payload->variantGroup,
            isPreorder: $payload->isPreorder,
            releaseDate: $payload->releaseDate,
        );
    }

    protected function worthAdding(Supplier $supplier, ProductPayload $payload, bool $insist = false): bool
    {

        $required = ! $insist && ($supplier->config['require_stock'] ?? config('import.require_stock', false));

        if ($required && $payload->stock <= 0) {
            Log::channel('import')->info('skipped: nothing in stock', [
                'supplier' => $supplier->code,
                'external' => $payload->externalId,
                'sku' => $payload->sku,
            ]);

            return false;
        }

        $floor = (float) ($supplier->config['min_cost'] ?? 0);

        if ($floor > 0 && $payload->costPrice < $floor) {
            Log::channel('import')->info('skipped: below the supplier floor', [
                'supplier' => $supplier->code,
                'external' => $payload->externalId,
                'sku' => $payload->sku,
                'cost' => $payload->costPrice,
                'floor' => $floor,
            ]);

            return false;
        }

        return true;
    }

    protected function keep(Supplier $supplier, Product $product): bool
    {
        $required = $supplier->config['require_image'] ?? config('import.require_image', false);

        if (! $required || $product->images()->exists()) {
            return true;
        }

        Log::channel('import')->info('dropped: no photograph', [
            'supplier' => $supplier->code,
            'sku' => $product->sku,
            'product' => $product->id,
        ]);

        $product->delete();

        return false;
    }

    protected function write(Supplier $supplier, ProductPayload $payload, bool $insist = false): array
    {
        try {
            return $this->writeInTransaction($supplier, $payload, $insist);
        } catch (\Throwable $e) {

            $this->taxonomy->forget();

            throw $e;
        }
    }

    protected function writeInTransaction(Supplier $supplier, ProductPayload $payload, bool $insist = false): array
    {
        return DB::transaction(function () use ($supplier, $payload, $insist) {
            $product = $this->find($supplier, $payload);
            $isNew = ! $product;

            if ($isNew && ! $this->worthAdding($supplier, $payload, $insist)) {
                return [null, false];
            }

            $product ??= new Product([
                'sku' => $payload->sku,
                'is_active' => false,
                'price' => $supplier->markup($payload->costPrice),
                'stock' => $payload->stock,
            ]);

            $this->fillTaxonomy($product, $supplier, $payload);

            $product->weight ??= $payload->weight;
            $product->is_preorder = $payload->isPreorder;
            $product->release_date = $payload->releaseDate;

            if ($payload->variantGroup) {
                $product->variant_group = $payload->variantGroup;
            }

            $product->synced_at = now();
            $product->save();

            ProductOffer::updateOrCreate(
                ['supplier_id' => $supplier->id, 'external_id' => $payload->externalId],
                [
                    'product_id' => $product->id,
                    'cost_price' => $payload->costPrice,
                    'old_cost_price' => $payload->oldCostPrice,
                    'stock' => $payload->stock,

                    'external_category' => $payload->categoryName,
                    'external_brand' => $payload->brandName,
                    'synced_at' => now(),
                ],
            );

            $this->refreshFromOffers($product);
            $this->syncTranslations($product, $payload);
            $this->syncSpecs($product, $supplier, $payload);
            Catalog::flush();

            if (! $product->category_id) {
                $this->log('imported without a category', $supplier, $payload, [
                    'product' => $product->id,
                    'external_name' => $payload->categoryName,
                ]);
            }

            $this->log($isNew ? 'imported (new)' : 'updated', $supplier, $payload, [
                'product' => $product->id,
                'stock' => $payload->stock,
                'price' => $product->price,
            ]);

            return [$product, $isNew];
        });
    }

    protected function find(Supplier $supplier, ProductPayload $payload): ?Product
    {
        $offer = ProductOffer::where('supplier_id', $supplier->id)
            ->where('external_id', $payload->externalId)
            ->first();

        if ($offer?->product) {
            return $offer->product;
        }

        return Product::where('sku', $payload->sku)
            ->whereDoesntHave('offers', fn ($q) => $q
                ->where('supplier_id', $supplier->id)
                ->where('external_id', '!=', $payload->externalId))
            ->first();
    }

    public function refreshFromOffers(Product $product): void
    {
        $offers = $product->offers()->with('supplier')->get();

        $best = $offers->sortBy([
            ['cost_price', 'asc'],
            fn ($a, $b) => $a->supplier->priority <=> $b->supplier->priority,
        ])->first();

        $product->stock = (int) $offers->sum('stock');

        if ($best) {
            $product->cost_price = $best->cost_price;

            if (! $product->price_lock) {
                $product->price = $best->supplier->markup((float) $best->cost_price);
                $product->old_price = $this->strikeThrough($best, (float) $product->price);
            }
        }

        $product->save();
    }

    protected function strikeThrough(ProductOffer $offer, float $price): ?float
    {
        if (! $offer->old_cost_price) {
            return null;
        }

        $was = $offer->supplier->markup((float) $offer->old_cost_price);

        return $was > $price ? $was : null;
    }

    protected function fillTaxonomy(Product $product, Supplier $supplier, ProductPayload $payload): void
    {

        $category = $this->taxonomy->category($supplier, $payload->categoryName);
        $brand = $this->taxonomy->brand($payload->brandName);

        if ($product->taxonomy_lock) {
            return;
        }

        $product->category_id = $category?->id ?? $product->category_id;
        $product->brand_id = $brand?->id ?? $product->brand_id;
    }

    protected function syncTranslations(Product $product, ProductPayload $payload): void
    {
        $rows = [];

        foreach ($payload->translations as $locale => $fields) {
            if (empty($fields['name'])) {
                continue;
            }

            $existing = $product->translate($locale, false);

            $rows[$locale] = [
                'name' => $this->fit($fields['name'], 255),

                'slug' => $existing?->slug,
                'subtitle' => $this->fit($fields['summary'] ?? $existing?->subtitle, 255),
                'description' => $fields['description'] ?? $existing?->description,
            ];
        }

        if (! $rows) {
            $this->log('skipped: no usable name in any language', null, $payload);

            return;
        }

        $product->saveTranslations($rows);
    }

    protected function fit(?string $value, int $max): ?string
    {
        if ($value === null || mb_strlen($value) <= $max) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $max - 1)).'…';
    }

    protected function syncSpecs(Product $product, Supplier $supplier, ProductPayload $payload): void
    {
        [$incoming, $derived] = $this->descriptionSpecs($product, $supplier, $payload);

        if (! $incoming) {
            $this->syncLaptopSsd($product, $supplier, $payload);

            return;
        }

        $valueIds = [];
        $order = 0;
        $specRows = [];
        $byAttribute = [];
        $now = now();

        foreach (collect($incoming)->groupBy('name') as $name => $rows) {
            $filterable = (bool) ($rows->first()['filterable'] ?? false);
            $description = $derived[(string) $name] ?? null;
            $attribute = $description['attribute'] ?? $this->taxonomy->productAttribute($supplier, $product, (string) $name, $rows->all(), $filterable);

            if (! $attribute) {
                continue;
            }

            $normalized = $this->taxonomy->normalizeProductRows($attribute, $rows->all(), $product);

            if ($normalized === []) {
                continue;
            }

            $rows = collect($normalized);
            $laptopSsd = (int) $attribute->id === LaptopSsd::attributeId()
                && $attribute->code === LaptopSsd::ATTRIBUTE_CODE
                && LaptopSsd::supports($product->category_id !== null ? (int) $product->category_id : null);
            $filterRows = $rows->all();

            if ($laptopSsd) {
                $filterRows = $this->ssdFilterRows($filterRows, $attribute->unit);
                if ($filterRows === []) {

                    $this->log('ssd filter skipped', $supplier, $payload, ['product' => $product->id, 'reason' => 'unsupported_incoming_capacity']);

                    continue;
                }
            }

            if (isset($byAttribute[$attribute->id])) {
                continue;
            }

            $value = $this->taxonomy->attributeValue($attribute, $filterRows);
            $measured = $attribute->code === RoomArea::ATTRIBUTE_CODE
                || ($attribute->code === ScreenSize::ATTRIBUTE_CODE
                    && in_array((int) $product->category_id, ImportRules::categoryIds('screen_size'), true));

            if (($measured && $value === null)
                || ($laptopSsd && ($value === null || ! $this->ssdValueMatches($value, $filterRows[0]['value'], $attribute->unit)))
                || ($description !== null && ($value === null || ! $this->descriptionValueMatches($attribute, $value, $description)))) {
                $this->log('import: measurement skipped', $supplier, $payload, [
                    'product_id' => $product->id,
                    'attribute' => $attribute->code,
                    'reason' => 'value_code_conflict',
                ]);

                continue;
            }

            $specRows[] = [
                'product_id' => $product->id,
                'attribute_id' => $attribute->id,
                'is_key' => (bool) ($rows->first()['key'] ?? false),
                'position' => $order++,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $byAttribute[$attribute->id] = $rows;

            if ($value !== null) {
                $valueIds[] = $value->id;
            }
        }

        if ($specRows) {
            ProductSpec::upsert($specRows, ['product_id', 'attribute_id'], ['is_key', 'position', 'updated_at']);

            $specIds = ProductSpec::where('product_id', $product->id)->pluck('id', 'attribute_id');

            $translations = [];

            foreach ($byAttribute as $attributeId => $rows) {
                $specId = $specIds[$attributeId] ?? null;

                if (! $specId) {
                    continue;
                }

                foreach ($rows as $row) {
                    if (! filled($row['value'] ?? null)) {
                        continue;
                    }

                    $translations[] = [
                        'product_spec_id' => $specId,
                        'locale' => $row['locale'],
                        'value' => $row['value'],
                    ];
                }
            }

            if ($translations) {
                ProductSpecTranslation::upsert($translations, ['product_spec_id', 'locale'], ['value']);
            }
        }

        $keep = $product->attributeValues()
            ->whereNotIn('attribute_values.attribute_id', array_keys($byAttribute))
            ->pluck('attribute_values.id');

        $product->attributeValues()->sync($keep->merge($valueIds)->unique()->all());
        $this->syncLaptopSsd($product, $supplier, $payload);
    }

    protected function ssdFilterRows(array $rows, ?string $unit): array
    {
        $normalized = [];
        $quantity = null;
        $defaultFound = false;
        foreach ($rows as $row) {
            $value = SsdCapacity::normalize($row['value'] ?? null, $unit);
            if ($value === null || ($quantity !== null && $quantity !== $value)) {
                return [];
            }
            $quantity = $value;
            $defaultFound = $defaultFound || ($row['locale'] ?? null) === Locales::default();
            $normalized[] = array_replace($row, ['value' => $value]);
        }

        return $defaultFound ? $normalized : [];
    }

    protected function ssdValueMatches(AttributeValue $value, string $expected, ?string $unit = null): bool
    {
        $defaultFound = false;
        foreach ($value->translations()->lockForUpdate()->get() as $translation) {
            if (SsdCapacity::normalize($translation->label, $unit) !== $expected) {
                return false;
            }
            $defaultFound = $defaultFound || $translation->locale === Locales::default();
        }

        return $defaultFound;
    }

    protected function syncLaptopSsd(Product $product, Supplier $supplier, ProductPayload $payload): void
    {
        if (! LaptopSsd::supports($product->category_id !== null ? (int) $product->category_id : null)) {
            return;
        }
        if ($product->attributeValues()->where('attribute_id', LaptopSsd::attributeId())->lockForUpdate()->exists()
            || $product->specs()->where('attribute_id', LaptopSsd::attributeId())->lockForUpdate()->exists()) {
            return;
        }
        $proof = LaptopSsd::forProduct($product, true);
        if ($proof === null) {
            return;
        }
        $attribute = Attribute::whereKey(LaptopSsd::attributeId())->where('code', LaptopSsd::ATTRIBUTE_CODE)
            ->where('is_filterable', true)->lockForUpdate()->first();
        if ($attribute === null) {
            return;
        }
        $rows = [];
        foreach ($proof['translations'] as $locale => $quantity) {
            $rows[] = ['name' => 'SSD მოცულობა', 'value' => $quantity, 'locale' => $locale, 'filterable' => true, 'key' => true];
        }
        $value = $this->taxonomy->attributeValue($attribute, $rows);
        if ($value === null || ! $this->ssdValueMatches($value, $proof['value'], $attribute->unit)) {
            $this->log('ssd filter skipped', $supplier, $payload, ['product' => $product->id, 'reason' => 'value_code_conflict']);

            return;
        }
        $product->attributeValues()->syncWithoutDetaching([$value->id]);
        $spec = ProductSpec::create(['product_id' => $product->id, 'attribute_id' => $attribute->id, 'is_key' => true,
            'position' => (int) $product->specs()->max('position') + 1]);
        $spec->saveTranslations(array_map(fn ($quantity) => ['value' => $quantity], $proof['translations']));
    }

    protected function descriptionSpecs(Product $product, Supplier $supplier, ProductPayload $payload): array
    {
        $family = ImportRules::family($product->category_id !== null ? (int) $product->category_id : null);

        if ($family === null) {
            return [$payload->specs, []];
        }

        $parser = new DescriptionSpecs;
        $incoming = $parser->sanitizeIncomingSpecs($family, $payload->specs);
        $profile = (array) config("import.families.$family", []);
        $kind = (string) ($profile['kind'] ?? '');
        $canonicalId = ImportRules::attributeId($profile['attribute'] ?? null);
        $aliases = [];

        foreach ((array) ($profile['aliases'] ?? []) as $alias) {
            $aliases[$this->descriptionKey($alias)] = true;
        }

        if ($aliases === [] || $canonicalId === null) {
            return [$incoming, []];
        }

        $claim = function (Attribute $attribute) use ($canonicalId, $aliases): bool {
            if ((int) $attribute->id === $canonicalId || isset($aliases[$this->descriptionKey($attribute->code)])) {
                return true;
            }

            foreach ($attribute->translations as $translation) {
                if (isset($aliases[$this->descriptionKey($translation->name)])) {
                    return true;
                }
            }

            return false;
        };

        foreach ([$product->attributeValues()->with('attribute.translations')->lockForUpdate()->get(), $product->specs()->with('attribute.translations')->lockForUpdate()->get()] as $records) {
            foreach ($records as $record) {
                if ($record->attribute !== null && $claim($record->attribute)) {
                    return [$incoming, []];
                }
            }
        }

        $names = array_values(array_unique(array_map(fn ($row) => (string) ($row['name'] ?? ''), $incoming)));
        foreach ($names as $name) {
            if (isset($aliases[$this->descriptionKey($name)])) {
                return [$incoming, []];
            }
        }

        $mapped = DB::table('supplier_attribute_map')->where('supplier_id', $supplier->id)->whereIn('external_name', $names)->pluck('attribute_id')->filter()->all();
        foreach (Attribute::whereIn('id', $mapped)->with('translations')->get() as $attribute) {
            if ($claim($attribute)) {
                return [$incoming, []];
            }
        }

        $candidate = $parser->extractTranslations($family, $payload->translations);

        if ($candidate === null) {
            return [$incoming, []];
        }

        $attribute = Attribute::whereKey($canonicalId)->where('code', $candidate['attribute_code'])->where('is_filterable', true)->first();

        if ($attribute === null) {
            $this->log('description filter skipped', $supplier, $payload, ['product' => $product->id, 'reason' => 'canonical_attribute_unavailable']);

            return [$incoming, []];
        }

        foreach (array_keys($candidate['locale_evidence']) as $locale) {
            $incoming[] = ['name' => $candidate['attribute_name'], 'value' => $candidate['value'],
                'locale' => $locale, 'filterable' => true, 'key' => true];
        }

        return [$incoming, [$candidate['attribute_name'] => ['attribute' => $attribute, 'kind' => $kind, 'value' => $candidate['value']]]];
    }

    protected function descriptionValueMatches(Attribute $attribute, AttributeValue $value, array $description): bool
    {
        $formatter = new PrimaryFeatureFormatter;
        $expected = $formatter->format($description['kind'], $description['value'], $attribute->unit);
        $defaultFound = false;

        foreach ($value->translations()->lockForUpdate()->get() as $translation) {
            if (trim((string) $translation->label) === ''
                || $formatter->format($description['kind'], $translation->label, $attribute->unit) !== $expected) {
                return false;
            }

            $defaultFound = $defaultFound || $translation->locale === Locales::default();
        }

        return $expected !== '' && $defaultFound;
    }

    protected function descriptionKey(string $value): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($value));
    }

    protected function log(string $message, ?Supplier $supplier, ?ProductPayload $payload, array $extra = []): void
    {
        Log::channel('import')->info($message, array_merge(array_filter([
            'supplier' => $supplier?->code,
            'external' => $payload?->externalId,
            'sku' => $payload?->sku,
            'name' => $payload ? ($payload->translations[array_key_first($payload->translations)]['name'] ?? null) : null,
        ]), $extra));
    }
}
