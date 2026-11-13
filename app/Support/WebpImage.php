<?php

namespace App\Support;

use Closure;
use Filament\Forms\Components\BaseFileUpload;
use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class WebpImage
{
    public static function isSupported(): bool
    {
        return function_exists('imagewebp') && function_exists('imagecreatefromstring');
    }

    public static function fromBytes(string $bytes): ?string
    {
        if (! static::isSupported() || $bytes === '') {
            return null;
        }

        $info = @getimagesizefromstring($bytes);

        if (! $info || ($info['mime'] ?? null) === 'image/webp' || static::isAnimatedGif($bytes, $info['mime'] ?? null)) {
            return null;
        }

        $image = @imagecreatefromstring($bytes);

        if (! $image instanceof GdImage) {
            return null;
        }

        try {
            $image = static::prepare($image);

            ob_start();
            $written = imagewebp($image, null, (int) config('images.webp_quality', 82));
            $webp = (string) ob_get_clean();
        } finally {
            imagedestroy($image);
        }

        return $written && $webp !== '' ? $webp : null;
    }

    public static function uploader(): Closure
    {
        return static function (BaseFileUpload $component, TemporaryUploadedFile $file): ?string {
            $webp = static::fromBytes((string) $file->get());

            if ($webp === null) {
                return $component->saveUploadedFile($file);
            }

            $name = Str::beforeLast($component->getUploadedFileNameForStorage($file), '.').'.webp';
            $path = trim($component->getDirectory().'/'.$name, '/');

            if (! $component->getDisk()->put($path, $webp, $component->getVisibility())) {
                return $component->saveUploadedFile($file);
            }

            $file->delete();

            return $path;
        };
    }

    public static function convertStored(string $disk, string $path): ?string
    {
        $storage = Storage::disk($disk);

        if (! $storage->exists($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'webp') {
            return null;
        }

        $webp = static::fromBytes((string) $storage->get($path));

        if ($webp === null) {
            return null;
        }

        $target = Str::beforeLast($path, '.').'.webp';

        if ($storage->exists($target)) {
            $target = Str::beforeLast($path, '.').'-'.substr(sha1($webp), 0, 8).'.webp';
        }

        return $storage->put($target, $webp) ? $target : null;
    }

    protected static function prepare(GdImage $image): GdImage
    {
        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        $maxSide = (int) config('images.max_side', 2000);
        $width = imagesx($image);
        $height = imagesy($image);

        if ($maxSide < 1 || max($width, $height) <= $maxSide) {
            return $image;
        }

        $scale = $maxSide / max($width, $height);
        $resized = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
        imagecopyresampled($resized, $image, 0, 0, 0, 0, imagesx($resized), imagesy($resized), $width, $height);
        imagedestroy($image);

        return $resized;
    }

    protected static function isAnimatedGif(string $bytes, ?string $mime): bool
    {
        return $mime === 'image/gif' && preg_match_all('/\x00\x21\xF9\x04.{4}\x00[\x2C\x21]/s', $bytes) > 1;
    }
}
