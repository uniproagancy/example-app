<?php

namespace App\Http\Controllers;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicFileController extends Controller
{
    public const MAX_AGE = 604800;

    public function __invoke(string $path): BinaryFileResponse
    {
        $disk = Storage::disk('public');

        if (str_contains($path, '..') || str_starts_with($path, '.') || ! $disk->fileExists($path)) {
            throw new HttpResponseException(response('', 404, ['Cache-Control' => 'no-store']));
        }

        return response()->file($disk->path($path), [
            'Cache-Control' => 'public, max-age='.self::MAX_AGE,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
