<?php

namespace App\Console\Commands;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Support\Catalog;
use App\Support\StandardAxes;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StandardizeFilters extends Command
{
    public const MIN_SHELF = 3;

    public const SHELF_SHARE = 0.25;

    public const LOG_DIRECTORY = 'standardize';

    protected $signature = 'catalog:standardize
        {--apply : add the values, which only reporting does not}
        {--undo= : roll back one earlier run, by the log file it printed}
        {--show=15 : how many examples to print per axis}';

    protected $description = 'Give storage, screen size and capacity one standard filter and variant each, without deleting anything';

    /**
     * @var array{ran_at: string, attributes_created: array<int, int>, attribute_flags: array<int, array<string, mixed>>, values_created: array<int, int>, attached: array<int, array{0: int, 1: int}>}
     */
    protected array $log = [];

    public function handle(): int
    {
        DB::connection()->disableQueryLog();

        if ($this->option('undo')) {
            return $this->undo((string) $this->option('undo'));
        }

        $apply = (bool) $this->option('apply');
        $this->log = ['ran_at' => now()->toIso8601String(), 'attributes_created' => [], 'attribute_flags' => [], 'values_created' => [], 'attached' => []];
        $attributes = Attribute::query()->withTrashed()->get(['id', 'code', 'is_filterable', 'is_variant', 'sort_order', 'deleted_at']);
        $products = $this->products();

        foreach (StandardAxes::axes() as $axis => $definition) {
            $canonical = $attributes->firstWhere('code', $axis);

            if ($canonical?->trashed()) {
                $this->warn("[$axis] a deleted filter with this code exists; restore or rename it first. Skipped.");

                continue;
            }

            $sources = $attributes->filter(fn (Attribute $attribute) => ! $attribute->trashed() && StandardAxes::isSource($axis, $attribute->code));
            $held = $canonical ? $this->holders($canonical->id) : [];
            [$found, $conflicts] = $this->fromSpecifications($axis, $sources->pluck('id')->all(), $held);
            $named = $definition['names'] ? $this->fromNames($axis, $products, $found, $held) : [];
            $answers = $found + $named;

            $this->report($axis, $definition, $products, $answers, $found, $named, $conflicts, count($held), $sources);

            if ($apply && $answers !== []) {
                DB::transaction(fn () => $this->file($axis, $definition, $canonical, $answers, $sources));
            }
        }

        if (! $apply) {
            $this->newLine();
            $this->line('Nothing was changed. Run again with --apply to add these values.');

            return self::SUCCESS;
        }

        $path = self::LOG_DIRECTORY.'/'.now()->format('Y-m-d-His').'.json';
        Storage::disk('local')->put($path, json_encode($this->log, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        Catalog::flush();

        $this->newLine();
        $this->info('Done. To roll this run back exactly: php artisan catalog:standardize --undo='.$path);

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, array{id: int, category: ?int, names: array<int, string>}>
     */
    protected function products(): Collection
    {
        $names = DB::table('product_translations')->whereIn('locale', ['ka', 'en'])->get(['product_id', 'name'])->groupBy('product_id');

        return Product::query()->get(['id', 'category_id'])->mapWithKeys(fn (Product $product) => [$product->id => [
            'id' => $product->id,
            'category' => $product->category_id,
            'names' => $names->get($product->id, collect())->pluck('name')->filter()->map(fn ($name) => (string) $name)->all(),
        ]]);
    }

    /**
     * @return array<int, true>
     */
    protected function holders(int $attributeId): array
    {
        return DB::table('attribute_value_product as avp')
            ->join('attribute_values as av', 'av.id', '=', 'avp.attribute_value_id')
            ->where('av.attribute_id', $attributeId)
            ->whereNull('av.deleted_at')
            ->pluck('avp.product_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /**
     * @param  array<int, int>  $sourceIds
     * @param  array<int, true>  $held
     * @return array{0: array<int, array{value: array{code: string, ka: string, en: string, sort: int}, from: string}>, 1: array<int, array<int, string>>}
     */
    protected function fromSpecifications(string $axis, array $sourceIds, array $held): array
    {
        if ($sourceIds === []) {
            return [[], []];
        }

        $codes = Attribute::query()->whereKey($sourceIds)->pluck('code', 'id');

        $specs = DB::table('product_specs as s')
            ->join('product_spec_translations as t', 't.product_spec_id', '=', 's.id')
            ->whereIn('s.attribute_id', $sourceIds)
            ->get(['s.product_id', 's.attribute_id', 't.value as text']);

        $values = DB::table('attribute_value_product as avp')
            ->join('attribute_values as av', 'av.id', '=', 'avp.attribute_value_id')
            ->join('attribute_value_translations as avt', 'avt.attribute_value_id', '=', 'av.id')
            ->whereIn('av.attribute_id', $sourceIds)
            ->whereNull('av.deleted_at')
            ->get(['avp.product_id', 'av.attribute_id', 'avt.label as text']);

        $seen = [];

        foreach ($specs->concat($values) as $row) {
            $product = (int) $row->product_id;

            if (isset($held[$product])) {
                continue;
            }

            $parsed = StandardAxes::parse($axis, (string) $row->text);

            if ($parsed !== null) {
                $seen[$product][$parsed['code']] = ['value' => $parsed, 'from' => $codes[$row->attribute_id].': '.trim((string) $row->text)];
            }
        }

        $found = [];
        $conflicts = [];

        foreach ($seen as $product => $answers) {
            if (count($answers) === 1) {
                $found[$product] = reset($answers);
            } else {
                $conflicts[$product] = array_column($answers, 'from');
            }
        }

        return [$found, $conflicts];
    }

    /**
     * @param  Collection<int, array{id: int, category: ?int, names: array<int, string>}>  $products
     * @param  array<int, mixed>  $found
     * @param  array<int, true>  $held
     * @return array<int, array{value: array{code: string, ka: string, en: string, sort: int}, from: string}>
     */
    protected function fromNames(string $axis, Collection $products, array $found, array $held): array
    {
        $sizes = $products->whereNotNull('category')->countBy('category');
        $shelves = $products->only(array_keys($found + $held))
            ->whereNotNull('category')
            ->countBy('category')
            ->filter(fn (int $count, int $category) => $count >= self::MIN_SHELF && $count >= $sizes[$category] * self::SHELF_SHARE);
        $named = [];

        foreach ($products as $id => $product) {
            if (isset($found[$id]) || isset($held[$id]) || $product['category'] === null || ! $shelves->has($product['category'])) {
                continue;
            }

            $answers = [];

            foreach ($product['names'] as $name) {
                $parsed = StandardAxes::fromName($axis, $name);

                if ($parsed !== null) {
                    $answers[$parsed['code']] = ['value' => $parsed, 'from' => 'name: '.$name];
                }
            }

            if (count($answers) === 1) {
                $named[$id] = reset($answers);
            }
        }

        return $named;
    }

    /**
     * @param  array{ka: string, en: string, sources: string, names: bool}  $definition
     * @param  array<int, array{value: array{code: string, ka: string, en: string, sort: int}, from: string}>  $answers
     * @param  Collection<int, Attribute>  $sources
     */
    protected function file(string $axis, array $definition, ?Attribute $canonical, array $answers, Collection $sources): void
    {
        if (! $canonical) {
            $canonical = Attribute::query()->create(['code' => $axis, 'type' => 'select', 'is_filterable' => true, 'is_variant' => true, 'sort_order' => StandardAxes::SORT_ORDER]);
            $canonical->saveTranslations(['ka' => ['name' => $definition['ka']], 'en' => ['name' => $definition['en']]]);
            $this->log['attributes_created'][] = $canonical->id;
        } elseif (! $canonical->is_filterable || ! $canonical->is_variant) {
            $this->log['attribute_flags'][] = ['id' => $canonical->id, 'is_filterable' => (bool) $canonical->is_filterable, 'is_variant' => (bool) $canonical->is_variant];
            DB::table('attributes')->where('id', $canonical->id)->update(['is_filterable' => true, 'is_variant' => true]);
        }

        $valueIds = AttributeValue::query()->where('attribute_id', $canonical->id)->pluck('id', 'code');
        $rows = [];

        foreach ($answers as $product => $answer) {
            $value = $answer['value'];

            if (! $valueIds->has($value['code'])) {
                $created = AttributeValue::query()->create(['attribute_id' => $canonical->id, 'code' => $value['code'], 'sort_order' => min(65535, $value['sort'])]);
                $created->saveTranslations(['ka' => ['label' => $value['ka']], 'en' => ['label' => $value['en']]]);
                $valueIds->put($value['code'], $created->id);
                $this->log['values_created'][] = $created->id;
            }

            $rows[] = ['product_id' => $product, 'attribute_value_id' => $valueIds[$value['code']]];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('attribute_value_product')->insertOrIgnore($chunk);
        }

        foreach ($rows as $row) {
            $this->log['attached'][] = [$row['product_id'], $row['attribute_value_id']];
        }

        foreach ($sources->where('is_filterable', true) as $source) {
            $this->log['attribute_flags'][] = ['id' => $source->id, 'is_filterable' => true, 'is_variant' => (bool) $source->is_variant];
            DB::table('attributes')->where('id', $source->id)->update(['is_filterable' => false]);
        }
    }

    /**
     * @param  array{ka: string, en: string, sources: string, names: bool}  $definition
     * @param  Collection<int, array{id: int, category: ?int, names: array<int, string>}>  $products
     * @param  array<int, array{value: array<string, mixed>, from: string}>  $answers
     * @param  array<int, mixed>  $found
     * @param  array<int, mixed>  $named
     * @param  array<int, array<int, string>>  $conflicts
     * @param  Collection<int, Attribute>  $sources
     */
    protected function report(string $axis, array $definition, Collection $products, array $answers, array $found, array $named, array $conflicts, int $held, Collection $sources): void
    {
        $show = max(0, (int) $this->option('show'));

        $this->newLine();
        $this->info("[$axis] {$definition['ka']} / {$definition['en']}");
        $this->line('  read from: '.($sources->pluck('code')->implode(', ') ?: '—'));
        $this->line('  already filed: '.$held.' · from specifications: '.count($found).' · from names: '.count($named).' · unclear, left alone: '.count($conflicts));

        $hidden = $sources->where('is_filterable', true)->pluck('code');

        if ($hidden->isNotEmpty()) {
            $this->line('  old filters to hide from the sidebar (kept, not deleted): '.$hidden->implode(', '));
        }

        $values = collect($answers)->countBy(fn (array $answer) => $answer['value']['ka'])->sortDesc();

        if ($values->isNotEmpty()) {
            $this->line('  values: '.$values->map(fn (int $count, string $label) => "$label ($count)")->take(25)->implode(', '));
        }

        foreach (array_slice($answers, 0, $show, true) as $product => $answer) {
            $this->line('    #'.$product.' '.mb_strimwidth($products[$product]['names'][0] ?? '', 0, 50, '…').' → '.$answer['value']['ka'].'   ('.mb_strimwidth($answer['from'], 0, 70, '…').')');
        }

        foreach (array_slice($conflicts, 0, min($show, 5), true) as $product => $froms) {
            $this->line('    unclear #'.$product.': '.mb_strimwidth(implode(' | ', $froms), 0, 110, '…'));
        }
    }

    protected function undo(string $path): int
    {
        $disk = Storage::disk('local');

        if (! str_starts_with($path, self::LOG_DIRECTORY.'/') || ! $disk->exists($path)) {
            $this->error('No such log: '.$path);

            return self::FAILURE;
        }

        $log = json_decode((string) $disk->get($path), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($log) {
            foreach (collect($log['attached'] ?? [])->groupBy(1) as $valueId => $pairs) {
                foreach ($pairs->pluck(0)->chunk(500) as $productIds) {
                    DB::table('attribute_value_product')->where('attribute_value_id', $valueId)->whereIn('product_id', $productIds->all())->delete();
                }
            }

            foreach ($log['values_created'] ?? [] as $valueId) {
                if (! DB::table('attribute_value_product')->where('attribute_value_id', $valueId)->exists()) {
                    DB::table('attribute_value_translations')->where('attribute_value_id', $valueId)->delete();
                    AttributeValue::query()->withTrashed()->whereKey($valueId)->forceDelete();
                }
            }

            foreach ($log['attributes_created'] ?? [] as $attributeId) {
                if (! AttributeValue::query()->withTrashed()->where('attribute_id', $attributeId)->exists()) {
                    DB::table('attribute_translations')->where('attribute_id', $attributeId)->delete();
                    Attribute::query()->withTrashed()->whereKey($attributeId)->forceDelete();
                }
            }

            foreach (array_reverse($log['attribute_flags'] ?? []) as $flags) {
                DB::table('attributes')->where('id', $flags['id'])->update(['is_filterable' => $flags['is_filterable'], 'is_variant' => $flags['is_variant']]);
            }
        });

        Catalog::flush();
        $disk->move($path, $path.'.undone');
        $this->info('Rolled back '.count($log['attached'] ?? []).' values. The log is kept as '.$path.'.undone');

        return self::SUCCESS;
    }
}
