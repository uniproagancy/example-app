<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Services\Import\VariantKey;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductVariants extends Command
{
    public const CHUNK = 100;

    protected $signature = 'products:variants
        {--supplier=alta : whose products to look at, by code}
        {--apply : file the families, which only reporting does not}
        {--regroup : re-file products this command itself grouped before, for when the rule has improved}
        {--show=30 : how many of each to print}';

    protected $description = 'Find a supplier\'s products that are one product in several colours or sizes, and the ones sent twice';

    public function handle(): int
    {
        $supplier = Supplier::where('code', $this->option('supplier'))->first();

        if (! $supplier) {
            $this->error('No supplier with code '.$this->option('supplier'));

            return self::FAILURE;
        }

        DB::connection()->disableQueryLog();

        $rows = $this->read($supplier->id, $supplier->code);

        if ($rows->isEmpty()) {
            $this->line('Nothing imported from '.$supplier->code.' yet.');

            return self::SUCCESS;
        }

        $this->line($rows->count().' products from '.$supplier->code);

        $this->reportDoubles($rows);
        $this->fileFamilies($rows, $supplier->code);

        return self::SUCCESS;
    }

    protected function read(int $supplierId, string $supplierCode): Collection
    {
        $rows = new Collection;

        Product::query()
            ->whereHas('offers', fn ($q) => $q->where('supplier_id', $supplierId))
            ->with(['brand', 'attributeValues' => fn ($q) => $q->withTranslation()->with('attribute')])
            ->withTranslation()
            ->chunkById(self::CHUNK, function (Collection $chunk) use ($rows, $supplierCode) {
                foreach ($chunk as $product) {
                    $rows->put($product->id, [
                        'id' => $product->id,
                        'name' => (string) $product->name,
                        'same' => VariantKey::normalise((string) $product->name),
                        'sku' => (string) $product->sku,
                        'group' => $product->variant_group,
                        'key' => $this->keyFor($supplierCode, $product),
                    ]);
                }
            });

        return $rows;
    }

    protected function reportDoubles(Collection $rows): void
    {
        $doubles = $rows
            ->groupBy('same')
            ->filter(fn (Collection $group, $same) => $same !== '' && $group->count() > 1);

        if ($doubles->isEmpty()) {
            return;
        }

        $this->line('');
        $this->line($doubles->count().' products imported more than once:');

        $this->table(
            ['name', 'how many', 'skus'],
            $doubles->take((int) $this->option('show'))->map(fn (Collection $group) => [
                $group->first()['name'],
                $group->count(),
                $group->pluck('sku')->implode("\n"),
            ])->values()->all(),
        );
    }

    protected function fileFamilies(Collection $rows, string $supplierCode): void
    {
        $regroup = (bool) $this->option('regroup');
        $prefix = $supplierCode.'-';

        $families = $rows
            ->filter(fn (array $row) => $row['key'] !== null)
            ->groupBy('key')

            ->filter(fn (Collection $group) => $group->pluck('same')->unique()->count() > 1);

        $established = Product::query()
            ->whereNotNull('variant_group')
            ->selectRaw('variant_group, count(*) as members')
            ->groupBy('variant_group')
            ->having('members', '>', 1)
            ->pluck('members', 'variant_group');

        $apply = (bool) $this->option('apply');
        $shown = (int) $this->option('show');
        $written = $kept = 0;
        $printed = [];
        $pending = [];

        foreach ($families as $key => $group) {
            $already = $group->filter(fn (array $row) => $row['group']
                && $row['group'] !== $key
                && $established->has($row['group'])

                && ! ($regroup && str_starts_with($row['group'], $prefix)));

            $kept += $already->count();

            $toWrite = $group
                ->whereNotIn('id', $already->pluck('id'))
                ->where('group', '!=', $key);

            if (count($printed) < $shown) {
                $printed[] = [
                    $key,
                    $group->count(),
                    $group->take(4)->pluck('name')->implode("\n"),
                ];
            }

            $written += $toWrite->count();

            if (! $apply) {
                continue;
            }

            foreach ($toWrite as $row) {
                $pending[$key][] = $row['id'];
            }

            if (count($pending) >= 50) {
                $this->write($pending);
                $pending = [];
            }
        }

        if ($pending) {
            $this->write($pending);
        }

        if ($printed) {
            $this->line('');
            $this->table(['family', 'products', 'what is in it'], $printed);
        }

        $this->line('');
        $this->line($families->count().' families of more than one');
        $this->line($apply
            ? $written.' products filed under one'
            : $written.' products would be filed under one (pass --apply to write)');

        if ($kept) {
            $this->line($kept.' left alone, already grouped by hand');
        }
    }

    protected function write(array $pending): void
    {
        foreach ($pending as $key => $ids) {
            Product::whereIn('id', $ids)->update(['variant_group' => $key]);
        }
    }

    protected function keyFor(string $supplierCode, Product $product): ?string
    {
        $name = trim((string) $product->name);

        if ($name === '') {
            return null;
        }

        $distinguishing = $product->attributeValues
            ->filter(fn ($value) => $value->attribute && VariantKey::isAxis((string) $value->attribute->code))
            ->map(fn ($value) => (string) $value->label)
            ->filter()
            ->values()
            ->all();

        return VariantKey::make($supplierCode, $product->brand?->name, $name, $distinguishing);
    }
}
