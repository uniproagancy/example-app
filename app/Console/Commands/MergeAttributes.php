<?php

namespace App\Console\Commands;

use App\Models\Attribute;
use App\Services\Import\TaxonomyResolver;
use App\Support\Locales;
use App\Support\Measure;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MergeAttributes extends Command
{
    public const LITTER = ['nbsp', 'bull', 'emsp', 'ensp', 'ndash', 'mdash', 'middot', 'amp', 'laquo', 'raquo'];

    protected $signature = 'attributes:merge
        {--apply : merge them, which only reporting does not}
        {--pair=* : two filters to join by hand, as code=code; the one more products are filed under keeps its place}
        {--keep-first : with --pair, the first filter named keeps its place and its name}
        {--show=25 : how many groups to print}';

    protected $description = 'Join filters that are the same filter written twice';

    public function handle(): int
    {
        DB::connection()->disableQueryLog();

        $locale = Locales::default();

        $attributes = DB::table('attributes')
            ->leftJoin('attribute_translations', function ($join) use ($locale) {
                $join->on('attribute_translations.attribute_id', '=', 'attributes.id')
                    ->where('attribute_translations.locale', '=', $locale);
            })
            ->leftJoin('product_specs', 'product_specs.attribute_id', '=', 'attributes.id')
            ->selectRaw('attributes.id, attributes.code, attributes.is_filterable, attributes.is_variant,
                attribute_translations.name, count(distinct product_specs.product_id) as products')
            ->groupBy('attributes.id', 'attributes.code', 'attributes.is_filterable', 'attributes.is_variant', 'attribute_translations.name')
            ->get();

        $groups = $this->option('pair')
            ? $this->pairs($attributes)
            : $attributes
                ->groupBy(fn ($row) => self::plain($row->name))
                ->forget('')
                ->filter(fn (Collection $group) => $group->count() > 1)
                ->sortByDesc(fn (Collection $group) => $group->sum('products'));

        if ($groups->isEmpty()) {
            $this->line('Every filter is written only once.');

            return self::SUCCESS;
        }

        $apply = (bool) $this->option('apply');
        $shown = (int) $this->option('show');
        $printed = $joined = 0;

        $this->line($attributes->count().' filters, '.$groups->count().' of them written more than once');
        $this->line('');

        foreach ($groups as $name => $group) {

            $group = $this->option('keep-first') && $this->option('pair')
                ? $group->values()
                : $group->sortByDesc('products')->values();
            $keeper = $group->first();
            $losers = $group->slice(1);

            if ($printed < $shown) {
                $this->line('  '.$name.'  → '.$keeper->code.' ('.$keeper->products.' products)');

                foreach ($losers as $loser) {
                    $this->line('       + '.$loser->code.'  '.$loser->products.' products');
                }

                $printed++;
            }

            $joined += $losers->count();

            if ($apply) {
                $this->fold($keeper, $losers);
            }
        }

        $this->line('');
        $this->line($apply
            ? $joined.' filters folded into another, leaving '.($attributes->count() - $joined)
            : $joined.' filters would be folded in, leaving '.($attributes->count() - $joined).' (pass --apply to do it)');

        return self::SUCCESS;
    }

    protected function pairs(Collection $attributes): Collection
    {
        $byCode = $attributes->keyBy('code');
        $groups = collect();

        foreach ($this->option('pair') as $pair) {
            $codes = array_values(array_filter(array_map('trim', explode('=', (string) $pair))));

            if (count($codes) < 2) {
                $this->error('A pair reads as code=code, not '.$pair);

                continue;
            }

            $found = collect($codes)->map(fn (string $code) => $byCode[$code] ?? null);

            if ($found->contains(null)) {
                $this->error('No filter called '.$found->filter()->isEmpty()
                    ? $pair
                    : collect($codes)->first(fn (string $code) => ! $byCode->has($code)));

                continue;
            }

            $groups[$pair] = $found->values();
        }

        return $groups;
    }

    protected function fold(object $keeper, Collection $losers): void
    {
        foreach ($losers as $loser) {
            DB::transaction(function () use ($keeper, $loser) {
                $this->carryUnit($loser);
                $this->moveValues((int) $keeper->id, (int) $loser->id);
                $this->moveSpecs((int) $keeper->id, (int) $loser->id);
                $this->moveShelves((int) $keeper->id, (int) $loser->id);

                DB::table('supplier_attribute_map')->where('attribute_id', $loser->id)
                    ->update(['attribute_id' => $keeper->id]);

                DB::table('attribute_translations')->where('attribute_id', $loser->id)->delete();
                DB::table('attributes')->where('id', $loser->id)->delete();
            });

            Attribute::whereKey($keeper->id)->update([
                'is_filterable' => (bool) ($keeper->is_filterable || $loser->is_filterable),
                'is_variant' => (bool) ($keeper->is_variant || $loser->is_variant),
            ]);

            $keeper->is_filterable = $keeper->is_filterable || $loser->is_filterable;
            $keeper->is_variant = $keeper->is_variant || $loser->is_variant;
        }
    }

    protected function moveValues(int $keeper, int $loser): void
    {
        $theirs = DB::table('attribute_values')->where('attribute_id', $keeper)->pluck('id', 'code');

        foreach (DB::table('attribute_values')->where('attribute_id', $loser)->get(['id', 'code']) as $value) {
            $same = $theirs[$value->code] ?? null;

            if (! $same) {
                DB::table('attribute_values')->where('id', $value->id)->update(['attribute_id' => $keeper]);

                continue;
            }

            $already = DB::table('attribute_value_product')->where('attribute_value_id', $same)->pluck('product_id');

            DB::table('attribute_value_product')->where('attribute_value_id', $value->id)
                ->whereIn('product_id', $already)->delete();

            DB::table('attribute_value_product')->where('attribute_value_id', $value->id)
                ->update(['attribute_value_id' => $same]);

            DB::table('attribute_value_translations')->where('attribute_value_id', $value->id)->delete();
            DB::table('attribute_values')->where('id', $value->id)->delete();
        }
    }

    protected function carryUnit(object $loser): void
    {
        $unit = self::unitOf((string) $loser->name);

        if ($unit === null) {
            return;
        }

        foreach (DB::table('attribute_values')->where('attribute_id', $loser->id)->get(['id']) as $value) {
            $labels = DB::table('attribute_value_translations')
                ->where('attribute_value_id', $value->id)->pluck('label', 'locale');

            if ($labels->isEmpty() || ! $labels->every(fn ($label) => self::bare((string) $label))) {
                continue;
            }

            foreach ($labels as $locale => $label) {
                DB::table('attribute_value_translations')
                    ->where('attribute_value_id', $value->id)->where('locale', $locale)
                    ->update(['label' => trim((string) $label).' '.$unit]);
            }

            $code = TaxonomyResolver::valueCode(trim((string) $labels->first()).' '.$unit);

            $twin = DB::table('attribute_values')->where('attribute_id', $loser->id)
                ->where('code', $code)->where('id', '!=', $value->id)->value('id');

            if (! $twin) {
                DB::table('attribute_values')->where('id', $value->id)->update(['code' => $code]);

                continue;
            }

            $already = DB::table('attribute_value_product')->where('attribute_value_id', $twin)->pluck('product_id');

            DB::table('attribute_value_product')->where('attribute_value_id', $value->id)
                ->whereIn('product_id', $already)->delete();
            DB::table('attribute_value_product')->where('attribute_value_id', $value->id)
                ->update(['attribute_value_id' => $twin]);
            DB::table('attribute_value_translations')->where('attribute_value_id', $value->id)->delete();
            DB::table('attribute_values')->where('id', $value->id)->delete();
        }

        $specs = DB::table('product_specs')->where('attribute_id', $loser->id)->pluck('id');

        DB::table('product_spec_translations')->whereIn('product_spec_id', $specs)
            ->get(['id', 'value'])
            ->filter(fn ($line) => self::bare((string) $line->value))
            ->each(fn ($line) => DB::table('product_spec_translations')->where('id', $line->id)
                ->update(['value' => trim((string) $line->value).' '.$unit]));
    }

    public static function unitOf(string $name): ?string
    {
        if (! preg_match_all('/\(([^)]*)\)/u', $name, $brackets)) {
            return null;
        }

        $unit = trim(str_replace('.', '', (string) end($brackets[1])));

        return $unit !== '' && Measure::read('1 '.$unit) !== null ? $unit : null;
    }

    protected static function bare(string $said): bool
    {
        return (bool) preg_match('/^\s*\d+(?:[.,]\d+)?\s*$/u', $said);
    }

    protected function moveSpecs(int $keeper, int $loser): void
    {
        $already = DB::table('product_specs')->where('attribute_id', $keeper)->pluck('product_id');

        DB::table('product_specs')->where('attribute_id', $loser)
            ->whereIn('product_id', $already)->delete();

        DB::table('product_specs')->where('attribute_id', $loser)
            ->update(['attribute_id' => $keeper]);
    }

    protected function moveShelves(int $keeper, int $loser): void
    {
        $open = DB::table('attribute_category')->where('attribute_id', $keeper)->doesntExist()
            || DB::table('attribute_category')->where('attribute_id', $loser)->doesntExist();

        if ($open) {
            DB::table('attribute_category')->whereIn('attribute_id', [$keeper, $loser])->delete();

            return;
        }

        $already = DB::table('attribute_category')->where('attribute_id', $keeper)->pluck('category_id');

        DB::table('attribute_category')->where('attribute_id', $loser)
            ->whereIn('category_id', $already)->delete();

        DB::table('attribute_category')->where('attribute_id', $loser)
            ->update(['attribute_id' => $keeper]);
    }

    public static function plain(?string $name): string
    {
        $name = html_entity_decode((string) $name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = str_replace(["\u{00a0}", "\u{2022}", "\u{2003}", "\u{2002}"], ' ', $name);
        $name = mb_strtolower(trim($name));
        $name = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name);
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        $words = array_values(array_filter(
            explode(' ', $name),
            fn (string $word) => $word !== '' && ! in_array($word, self::LITTER, true),
        ));

        return implode(' ', $words);
    }
}
