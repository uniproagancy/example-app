<?php

namespace App\Console\Commands;

use App\Support\Catalog;
use App\Support\Locales;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FiltersOff extends Command
{
    public const BACKUP_DIRECTORY = 'filters-backup';

    protected $signature = 'filters:off
        {--apply : switch the filters off, which only listing them does not}
        {--restore= : switch back on the filters saved in this backup file (a name from storage/app/filters-backup)}';

    protected $description = 'Switch every storefront filter off, keeping a backup so they can be switched back on';

    public function handle(): int
    {
        if ($this->option('restore')) {
            return $this->restore((string) $this->option('restore'));
        }

        $filters = $this->filters();

        if ($filters->isEmpty()) {
            $this->info('No filter is switched on.');

            return self::SUCCESS;
        }

        $this->table(['id', 'code', 'name'], $filters->map(fn (object $filter) => [$filter->id, $filter->code, $filter->name])->all());

        if (! $this->option('apply')) {
            $this->warn("{$filters->count()} filters are switched on. Nothing was changed. Run again with --apply to switch them off.");

            return self::SUCCESS;
        }

        $file = self::BACKUP_DIRECTORY.'/filters-off-'.now()->format('Ymd-His').'.json';
        Storage::disk('local')->put($file, json_encode($filters->map(fn (object $filter) => ['id' => $filter->id, 'code' => $filter->code])->values()->all(), JSON_PRETTY_PRINT));

        $switched = DB::table('attributes')->whereIn('id', $filters->pluck('id'))->update(['is_filterable' => false, 'updated_at' => now()]);
        Catalog::flush();

        $this->info("{$switched} filters switched off. Backup: storage/app/{$file}");
        $this->line('Switch them back on one by one in admin → Attributes, or all at once with: php artisan filters:off --restore='.basename($file));

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, object{id: int, code: string, name: ?string}>
     */
    protected function filters(): Collection
    {
        return DB::table('attributes as a')
            ->leftJoin('attribute_translations as t', fn ($join) => $join->on('t.attribute_id', '=', 'a.id')->where('t.locale', Locales::default()))
            ->whereNull('a.deleted_at')
            ->where('a.is_filterable', true)
            ->orderBy('a.sort_order')
            ->orderBy('a.id')
            ->get(['a.id', 'a.code', 't.name']);
    }

    protected function restore(string $name): int
    {
        $file = self::BACKUP_DIRECTORY.'/'.basename($name);

        if (! Storage::disk('local')->exists($file)) {
            $this->error("Backup not found: storage/app/{$file}");

            return self::FAILURE;
        }

        $saved = collect(json_decode((string) Storage::disk('local')->get($file), true) ?: []);
        $ids = $saved->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

        if ($ids === []) {
            $this->error('The backup lists no filters.');

            return self::FAILURE;
        }

        $restored = DB::table('attributes')->whereIn('id', $ids)->whereNull('deleted_at')->update(['is_filterable' => true, 'updated_at' => now()]);
        Catalog::flush();

        $this->info("{$restored} of ".count($ids).' filters switched back on.');

        return self::SUCCESS;
    }
}
