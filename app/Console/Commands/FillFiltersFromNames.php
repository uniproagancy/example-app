<?php

namespace App\Console\Commands;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Services\Import\TaxonomyResolver;
use App\Support\Catalog;
use App\Support\Locales;
use App\Support\Measure;
use App\Support\Palette;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FillFiltersFromNames extends Command
{
    public const MIN_SHELF = 20;

    protected const MEASURED = 0.6;

    protected const SAME = 0.0001;

    protected const HEADLINE = [
        'volume' => ['mtliani-motsuloba', 'motsuloba'],
        'heat' => ['gagrilebis-simdzlavre-btu'],
        'weight' => ['maksimaluri-chatvirtva', 'maksimaluri-datvirtva'],
        'power' => ['max-vati', 'max-simdzlavre', 'simdzlavre'],
        'length' => ['ekranis-zoma', 'screen'],
    ];

    protected const MEMORY = [
        'operatiuli-mekhsiereba' => 'ram',
        'shida-mekhsiereba' => 'storage',
    ];

    protected const MOST_RAM_GB = 64;

    protected const LEAST_STORAGE_GB = 64;

    protected const NOT_FROM_NAMES = ['tsona'];

    protected const PLAUSIBLE = 1.5;

    protected const SPREAD = 2;

    protected $signature = 'products:fill-from-names
        {--apply : file them, which only reporting does not}
        {--show=30 : how many examples to print}';

    protected $description = 'File products under their shelf\'s filters where only their name gives the answer';

    protected array $answers = [];

    public function handle(TaxonomyResolver $taxonomy): int
    {
        DB::connection()->disableQueryLog();

        app()->setLocale($locale = Locales::default());

        $apply = (bool) $this->option('apply');
        $shown = (int) $this->option('show');
        $printed = 0;
        $filed = [];
        $stocked = Catalog::stocked();

        foreach (Category::active()->withTranslation()->get() as $shelf) {
            if (($stocked[$shelf->id] ?? 0) < self::MIN_SHELF) {
                continue;
            }

            $products = Product::listable()->where('category_id', $shelf->id)->withTranslation()
                ->with('brand:id,name')
                ->get(['id', 'variant_group', 'brand_id']);

            if ($products->count() < self::MIN_SHELF) {
                continue;
            }

            foreach ($this->fillable(Catalog::listing($shelf)['groups']) as [$group, $attribute, $kind, $range]) {

                $answered = DB::table('attribute_value_product as avp')
                    ->join('attribute_values as av', 'av.id', '=', 'avp.attribute_value_id')
                    ->where('av.attribute_id', $attribute->id)
                    ->whereIn('avp.product_id', $products->pluck('id'))
                    ->distinct()->pluck('avp.product_id')->flip();

                foreach ($products as $product) {
                    if (isset($answered[$product->id])) {
                        continue;
                    }

                    $label = $kind === 'colour'
                        ? $this->colourFor($attribute, $product)
                        : ($kind === 'memory'
                            ? $this->memoryFor($attribute, $range, (string) $product->name)
                            : $this->readingFor($kind, $range, (string) $product->name));

                    if ($label === null) {
                        continue;
                    }

                    $filed[$group['name']] = ($filed[$group['name']] ?? 0) + 1;

                    if ($printed < $shown) {
                        $this->line('  '.str_pad(mb_substr($group['name'], 0, 24), 26).str_pad($label, 14).mb_substr((string) $product->name, 0, 60));
                        $printed++;
                    }

                    if (! $apply) {
                        continue;
                    }

                    $value = $this->existing($attribute, $label)
                        ?? $taxonomy->attributeValue($attribute, [['locale' => $locale, 'value' => $label, 'color' => null]]);

                    if ($value) {
                        DB::table('attribute_value_product')->insertOrIgnore([
                            'product_id' => $product->id,
                            'attribute_value_id' => $value->id,
                        ]);
                    }
                }
            }

            $taxonomy->forget();
        }

        $this->line('');

        arsort($filed);

        foreach ($filed as $filter => $count) {
            $this->line('  '.str_pad(mb_substr($filter, 0, 34), 36).$count);
        }

        $this->line('');
        $this->line(($apply ? 'filed ' : 'would file ').array_sum($filed).' products under a filter their name answers'
            .($apply ? '' : ' (pass --apply to do it)'));

        return self::SUCCESS;
    }

    protected function kindOf(Attribute $attribute, array $group): ?string
    {
        if (in_array($attribute->code, self::NOT_FROM_NAMES, true)) {
            return null;
        }

        if ($attribute->type === Attribute::TYPE_COLOR) {
            return 'colour';
        }

        $readings = collect($group['items'])->map(fn ($item) => Measure::read($item['label']))->filter();

        if ($readings->count() < count($group['items']) * self::MEASURED) {
            return null;
        }

        $dimensions = $readings->pluck('dimension')->unique();

        if ($dimensions->count() !== 1) {
            return null;
        }

        $dimension = (string) $dimensions->first();

        if ($dimension === 'memory' && ! isset(self::MEMORY[$attribute->code])) {
            return null;
        }

        if ($dimension === 'length' && ($readings->pluck('unit')->countBy()->sortDesc()->keys()->first() !== 'inch')) {
            return null;
        }

        return $dimension;
    }

    protected function fillable(array $groups): array
    {
        $found = [];

        foreach ($groups as $group) {
            if ($group['key'] === 'brand') {
                continue;
            }

            $attribute = Attribute::where('code', $group['key'])->first();
            $kind = $attribute ? $this->kindOf($attribute, $group) : null;

            if ($kind) {
                $found[] = [$group, $attribute, $kind, $kind === 'colour' ? null : $this->rangeOf($group)];
            }
        }

        return collect($found)
            ->groupBy(fn (array $one) => $one[2])
            ->flatMap(function (Collection $same, string $kind) {

                if ($same->count() === 1 || $kind === 'colour' || $kind === 'memory') {
                    return $same->all();
                }

                foreach (self::HEADLINE[$kind] ?? [] as $code) {
                    $headline = $same->first(fn (array $one) => $one[1]->code === $code);

                    if ($headline) {
                        return [$headline];
                    }
                }

                return [];
            })

            ->filter(fn (array $one) => $one[2] === 'colour' || $one[3] !== null)
            ->values()->all();
    }

    protected function rangeOf(array $group): ?array
    {
        $sizes = collect($group['items'])
            ->map(fn (array $item) => Measure::read($item['label']))
            ->filter()
            ->flatMap(fn (array $reading) => array_values(array_filter([$reading['low'], $reading['high']], fn ($size) => $size !== null)))
            ->sort()->values();

        if ($sizes->count() < self::SPREAD) {
            return null;
        }

        $last = $sizes->count() - 1;
        $low = (float) $sizes[(int) floor($last * 0.1)];
        $high = (float) $sizes[(int) ceil($last * 0.9)];

        return [$low / self::PLAUSIBLE, $high * self::PLAUSIBLE];
    }

    protected function readingFor(string $dimension, ?array $range, string $name): ?string
    {
        $found = collect(Measure::mentioned($name))
            ->filter(fn (array $reading) => $reading['dimension'] === $dimension)
            ->unique(fn (array $reading) => round($reading['low'], 3).'-'.round((float) $reading['high'], 3));

        if ($dimension === 'length') {
            $found = $found->filter(fn (array $reading) => $reading['unit'] === 'inch');
        }

        if ($found->count() !== 1) {
            return null;
        }

        $reading = $found->first();

        if ($range && ($reading['low'] < $range[0] || ($reading['high'] ?? $reading['low']) > $range[1])) {
            return null;
        }

        return Measure::canonical($reading);
    }

    protected function memoryFor(Attribute $attribute, ?array $range, string $name): ?string
    {
        $role = self::MEMORY[$attribute->code];
        $gigabyte = Measure::factor('memory', 'gb');
        $said = mb_strtolower($name);
        $found = [];

        if (preg_match('/(?<![\d.])(\d+)\s*(?:gb)?\s*[\/+]\s*(\d+)\s*(gb|tb)(?![\p{L}\d])/u', $said, $pair)) {
            $found[] = (float) $pair[1] * $gigabyte;
            $found[] = (float) $pair[2] * Measure::factor('memory', $pair[3]);
            $said = str_replace($pair[0], ' ', $said);
        }

        foreach (Measure::mentioned($said) as $reading) {
            if ($reading['dimension'] === 'memory') {
                $found[] = $reading['low'];
            }
        }

        $found = array_values(array_unique($found));
        sort($found);

        $size = match (true) {
            count($found) === 1 => $role === 'storage' && $found[0] >= self::LEAST_STORAGE_GB * $gigabyte ? $found[0] : null,
            count($found) === 2 && $found[0] <= self::MOST_RAM_GB * $gigabyte && $found[1] >= 4 * $found[0] => $role === 'ram' ? $found[0] : $found[1],
            default => null,
        };

        if ($size === null || ($range && ($size < $range[0] || $size > $range[1]))) {
            return null;
        }

        $unit = $size >= Measure::factor('memory', 'tb') ? 'tb' : 'gb';

        return Measure::canonical(['dimension' => 'memory', 'unit' => $unit, 'low' => $size, 'high' => null]);
    }

    protected function colourFor(Attribute $attribute, Product $product): ?string
    {
        $base = Palette::named((string) $product->name, $product->brand?->name);

        if (! $base) {
            return null;
        }

        $answers = $this->answersOf($attribute)->where('base', $base);

        if ($answers->isEmpty()) {
            return null;
        }

        if ($product->variant_group) {
            $family = DB::table('attribute_value_product as avp')
                ->join('products as p', 'p.id', '=', 'avp.product_id')
                ->where('p.variant_group', $product->variant_group)
                ->whereIn('avp.attribute_value_id', $answers->pluck('id'))
                ->selectRaw('avp.attribute_value_id as id, count(*) as n')
                ->groupBy('avp.attribute_value_id')
                ->orderByDesc('n')
                ->value('id');

            if ($family) {
                return (string) $answers->firstWhere('id', $family)->label;
            }
        }

        return (string) $answers->sortByDesc('held')->first()->label;
    }

    protected function existing(Attribute $attribute, string $label): ?object
    {
        $wanted = Measure::read($label);
        $answers = $this->answersOf($attribute);

        if (! $wanted) {
            return $answers->firstWhere('label', $label);
        }

        return $answers
            ->filter(fn ($answer) => $answer->reading
                && $answer->reading['dimension'] === $wanted['dimension']
                && abs($answer->reading['low'] - $wanted['low']) <= $wanted['low'] * self::SAME
                && ($answer->reading['high'] === null) === ($wanted['high'] === null)
                && ($wanted['high'] === null || abs($answer->reading['high'] - $wanted['high']) <= $wanted['high'] * self::SAME))
            ->sortByDesc('held')
            ->first();
    }

    protected function answersOf(Attribute $attribute): Collection
    {
        return $this->answers[$attribute->id] ??= DB::table('attribute_values as av')
            ->join('attribute_value_translations as t', function ($join) {
                $join->on('t.attribute_value_id', '=', 'av.id')->where('t.locale', '=', Locales::default());
            })
            ->leftJoin('attribute_value_product as avp', 'avp.attribute_value_id', '=', 'av.id')
            ->where('av.attribute_id', $attribute->id)
            ->selectRaw('av.id, t.label, count(avp.product_id) as held')
            ->groupBy('av.id', 't.label')
            ->get()
            ->map(function ($answer) {
                $answer->reading = Measure::read($answer->label);
                $answer->base = Palette::baseOf($answer->label);

                return $answer;
            });
    }
}
