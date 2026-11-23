<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class JsonUploadController extends Controller
{
    public const CHUNK_BYTES = 5 * 1024 * 1024;

    public const MAX_BYTES = 5 * 1024 * 1024 * 1024;

    public static function path(int $userId, string $upload): string
    {
        return 'json-uploads/'.$userId.'/'.$upload.'.json';
    }

    public function __invoke(Request $request, string $upload): JsonResponse
    {
        $user = $request->user();

        abort_unless($user, 401);
        abort_unless($user->is_admin === true, 403);
        abort_unless(Str::isUuid($upload), 404);

        $offset = (int) $request->header('X-Upload-Offset', '-1');
        $chunk = $request->getContent();
        $disk = Storage::disk('local');
        $path = static::path($user->id, $upload);

        if ($chunk === '' || strlen($chunk) > self::CHUNK_BYTES || $offset < 0 || $offset + strlen($chunk) > self::MAX_BYTES) {
            abort(422);
        }

        if ($offset === 0) {
            $this->prune($user->id);
            $disk->put($path, '');
        }

        $size = $disk->exists($path) ? $disk->size($path) : -1;

        if ($size !== $offset) {
            return response()->json(['size' => max(0, $size)], 409);
        }

        file_put_contents($disk->path($path), $chunk, FILE_APPEND | LOCK_EX);

        return response()->json(['size' => $offset + strlen($chunk)]);
    }

    protected function prune(int $userId): void
    {
        $disk = Storage::disk('local');

        foreach ($disk->files('json-uploads/'.$userId) as $file) {
            if ($disk->lastModified($file) < now()->subDay()->getTimestamp()) {
                $disk->delete($file);
            }
        }
    }
}
