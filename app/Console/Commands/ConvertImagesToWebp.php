<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Slide;
use App\Support\Catalog;
use App\Support\WebpImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ConvertImagesToWebp extends Command
{
    protected $signature = 'images:webp
        {--apply : convert the files and point the records at them, which only reporting does not}
        {--limit=0 : stop after this many product pictures (0 = all)}';

    protected $description = 'Convert product, slide and logo pictures that are not WebP yet';

    protected int $savedBytes = 0;

    public function handle(): int
    {
        if (! WebpImage::isSupported()) {
            $this->error('This PHP has no WebP support in GD.');

            return self::FAILURE;
        }

        DB::connection()->disableQueryLog();
        $apply = (bool) $this->option('apply');
        $limit = max(0, (int) $this->option('limit'));

        $paths = DB::table('product_images')
            ->where('path', 'not like', '%.webp')
            ->distinct()
            ->orderBy('path')
            ->when($limit, fn ($query) => $query->limit($limit))
            ->pluck('path');

        $this->line($paths->count().' product picture files are not WebP yet');

        if (! $apply) {
            $this->line('nothing was changed; pass --apply to convert them');

            return self::SUCCESS;
        }

        [$converted, $skipped] = [0, 0];
        $bar = $this->output->createProgressBar($paths->count());

        foreach ($paths as $path) {
            $target = $this->convert($path);

            if ($target === null) {
                $skipped++;
            } else {
                DB::table('product_images')->where('path', $path)->update(['path' => $target, 'updated_at' => now()]);
                $converted++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        foreach (Slide::query()->whereNotNull('image')->where('image', 'not like', '%.webp')->get() as $slide) {
            if ($target = $this->convert($slide->image)) {
                $slide->update(['image' => $target]);
                $converted++;
            }
        }

        $logo = Setting::get('logo');

        if (filled($logo) && ($target = $this->convert($logo))) {
            Setting::put('logo', $target);
            $converted++;
        }

        Catalog::flush();

        $this->info($converted.' converted, '.$skipped.' left as they were (missing, animated or unreadable)');
        $this->line(number_format($this->savedBytes / 1048576, 1).' MB saved');

        return self::SUCCESS;
    }

    protected function convert(string $path): ?string
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        $before = $disk->size($path);
        $target = WebpImage::convertStored('public', $path);

        if ($target === null) {
            return null;
        }

        $this->savedBytes += $before - $disk->size($target);
        $disk->delete($path);

        return $target;
    }
}
