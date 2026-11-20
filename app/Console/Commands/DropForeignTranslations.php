<?php

namespace App\Console\Commands;

use App\Support\Catalog;
use App\Support\Locales;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[Signature('translations:drop-foreign
    {--force : delete for real; without it nothing is touched}
    {--undo= : put back the rows saved in this backup file}')]
#[Description('Delete translations in languages the site no longer offers, keeping a backup that --undo restores')]
class DropForeignTranslations extends Command
{
    public const TABLES = [
        'attribute_translations',
        'attribute_value_translations',
        'category_translations',
        'page_translations',
        'payment_method_translations',
        'product_spec_translations',
        'product_translations',
    ];

    protected const DISK = 'local';

    protected const DIRECTORY = 'translation-backups';

    public function handle(): int
    {
        if ($this->option('undo')) {
            return $this->undo((string) $this->option('undo'));
        }

        $keep = Locales::codes();
        $counts = [];

        foreach (self::TABLES as $table) {
            $counts[$table] = DB::table($table)->whereNotIn('locale', $keep)->count();
        }

        $this->line('Languages kept: '.implode(', ', $keep));
        $this->table(['table', 'rows to delete'], collect($counts)->map(fn (int $count, string $table) => [$table, $count])->values()->all());

        if (array_sum($counts) === 0) {
            $this->info('Nothing to delete.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->warn('Nothing was deleted. Add --force to do it for real; a backup is written first.');

            return self::SUCCESS;
        }

        $backup = [];

        foreach (self::TABLES as $table) {
            $backup[$table] = DB::table($table)->whereNotIn('locale', $keep)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        $path = self::DIRECTORY.'/'.now()->format('Y-m-d_His').'.json';
        $json = json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        if ($json === false || ! Storage::disk(self::DISK)->put($path, $json)) {
            $this->error('The backup could not be written, so nothing was deleted.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($backup): void {
            foreach ($backup as $table => $rows) {
                foreach (array_chunk(array_column($rows, 'id'), 500) as $ids) {
                    DB::table($table)->whereIn('id', $ids)->delete();
                }
            }
        });

        Catalog::flush();

        $this->info('Deleted '.array_sum($counts).' rows. Backup: '.Storage::disk(self::DISK)->path($path));
        $this->line('To put them back: php artisan translations:drop-foreign --undo='.$path);

        return self::SUCCESS;
    }

    protected function undo(string $path): int
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (! str_starts_with($path, self::DIRECTORY.'/') || str_contains($path, '..') || ! Storage::disk(self::DISK)->exists($path)) {
            $this->error('No backup at '.$path.'. Backups are listed in '.Storage::disk(self::DISK)->path(self::DIRECTORY));

            return self::FAILURE;
        }

        $backup = json_decode((string) Storage::disk(self::DISK)->get($path), true);

        if (! is_array($backup)) {
            $this->error('The backup file is not readable.');

            return self::FAILURE;
        }

        $restored = 0;

        DB::transaction(function () use ($backup, &$restored): void {
            foreach ($backup as $table => $rows) {
                if (! in_array($table, self::TABLES, true)) {
                    continue;
                }

                foreach (array_chunk($rows, 200) as $chunk) {
                    $missing = array_values(array_filter($chunk, fn (array $row) => ! DB::table($table)->where('id', $row['id'])->exists()));
                    DB::table($table)->insert($missing);
                    $restored += count($missing);
                }
            }
        });

        Catalog::flush();
        $this->info("Restored {$restored} rows.");

        return self::SUCCESS;
    }
}
