<?php

namespace App\Support;

use Illuminate\Foundation\Vite;
use Illuminate\Foundation\ViteFonts;

class ServedVite extends Vite
{
    public static function servedPath(string $relative): ?string
    {
        $root = request()->server('DOCUMENT_ROOT');

        if (! is_string($root) || $root === '' || ! is_dir($root)) {
            return null;
        }

        if (realpath($root) === realpath(public_path())) {
            return null;
        }

        $path = rtrim($root, '/\\').'/'.ltrim($relative, '/\\');

        return is_file($path) ? $path : null;
    }

    public static function manifestFile(): string
    {
        return static::servedPath('build/manifest.json') ?? public_path('build/manifest.json');
    }

    protected function manifestPath($buildDirectory)
    {
        return static::servedPath($buildDirectory.'/'.$this->manifestFilename) ?? parent::manifestPath($buildDirectory);
    }

    protected function viteFonts()
    {
        return $this->fonts ??= new class extends ViteFonts
        {
            public function manifest(bool $isHot, string $buildDirectory, string $manifestFilename, string $hotFile)
            {
                if (! $isHot && ($served = ServedVite::servedPath($buildDirectory.'/'.$manifestFilename))) {
                    return $this->readManifest($served);
                }

                return parent::manifest($isHot, $buildDirectory, $manifestFilename, $hotFile);
            }
        };
    }
}
