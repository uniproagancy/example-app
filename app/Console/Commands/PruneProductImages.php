<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PruneProductImages extends Command
{
    public const FOLDER = 'products';

    public const THUMBS = 'thumbs';

    protected $signature = 'images:prune
        {--apply : delete them, which only reporting does not}
        {--show=15 : how many to print}';

    protected $description = 'Remove product pictures no product points at any more';

    public function handle(): int
    {
        DB::connection()->disableQueryLog();

        $disk = Storage::disk('public');

        if (! $disk->exists(self::FOLDER)) {
            $this->error('No '.self::FOLDER.' folder on this machine; run this where the files are.');

            return self::FAILURE;
        }

        $paths = DB::table('product_images')->pluck('path');
        $kept = $paths->flip();

        $stems = $paths->map(fn (string $path) => self::stem($path))->flip();

        $files = $disk->allFiles(self::FOLDER);

        $orphans = array_values(array_filter(
            $files,
            fn (string $file) => ! $kept->has($file),
        ));

        foreach ($disk->exists(self::THUMBS) ? $disk->allFiles(self::THUMBS) : [] as $file) {

            $original = self::stem((string) preg_replace('#^'.self::THUMBS.'/[^/]+/#', '', $file));

            if (! $stems->has($original)) {
                $orphans[] = $file;
            }
        }

        $this->line(count($files).' picture files, '.$kept->count().' of them spoken for');
        $this->line(count($orphans).' that nothing points at, thumbnails included');

        if (! $orphans) {
            return self::SUCCESS;
        }

        $bytes = 0;

        foreach ($orphans as $file) {
            $bytes += $disk->size($file);
        }

        $this->line(number_format($bytes / 1048576, 1).' MB');
        $this->line('');

        foreach (array_slice($orphans, 0, (int) $this->option('show')) as $file) {
            $this->line('  '.$file);
        }

        if (! $this->option('apply')) {
            $this->line('');
            $this->line('nothing was deleted; pass --apply to delete them');

            return self::SUCCESS;
        }

        foreach (array_chunk($orphans, 200) as $chunk) {
            $disk->delete($chunk);
        }

        $this->line('');
        $this->line(count($orphans).' files deleted');

        return self::SUCCESS;
    }

    protected static function stem(string $path): string
    {
        return (string) preg_replace('/\.[^.\/]+$/', '', $path);
    }
}
