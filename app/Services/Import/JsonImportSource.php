<?php

namespace App\Services\Import;

use Illuminate\Support\Str;

class JsonImportSource
{
    public const CHUNK_BYTES = 4194304;

    public const MAX_RECORD_BYTES = 67108864;

    public const TTL_SECONDS = 172800;

    protected string $root;

    public function __construct(?string $directory = null)
    {
        $this->root = rtrim($directory ?? storage_path('app/private/json-imports'), '/\\');
    }

    public function create(int $userId, string $supplierCode, string $filename, int $totalBytes): array
    {
        if ($userId < 1 || ! preg_match('/\A[A-Za-z0-9_-]{1,64}\z/D', $supplierCode)
            || $totalBytes < 0 || $totalBytes > 9007199254740991 || ! mb_check_encoding($filename, 'UTF-8')
            || preg_match('/[\x00-\x1f\x7f]/', $filename)) {
            $this->fail('json_source_invalid');
        }

        $filename = basename(str_replace('\\', '/', $filename));

        if (! preg_match('/\.json\z/iD', $filename) || strlen($filename) > 255) {
            $this->fail('json_source_invalid');
        }

        $base = $this->base();
        $this->space($base, $totalBytes);
        $parent = $base.DIRECTORY_SEPARATOR.$userId;
        $this->makeDirectory($parent);
        $id = (string) Str::uuid();
        $directory = $parent.DIRECTORY_SEPARATOR.$id;
        $this->makeDirectory($directory);

        foreach (['upload.part', 'chunks.sha256', '.lock'] as $name) {
            $handle = @fopen($directory.DIRECTORY_SEPARATOR.$name, 'x+b');

            if (! is_resource($handle)) {
                $this->fail('json_source_unavailable', 500);
            }

            fclose($handle);
            @chmod($directory.DIRECTORY_SEPARATOR.$name, 0600);
        }

        $meta = ['id' => $id, 'user_id' => $userId, 'supplier' => $supplierCode, 'name' => $filename,
            'size' => $totalBytes, 'received' => 0, 'state' => 'uploading', 'sha256' => null,
            'created_at' => now()->timestamp, 'expires_at' => now()->timestamp + self::TTL_SECONDS];
        $this->writeMeta($directory, $meta);

        return $this->publicInfo($meta);
    }

    public function append(int $userId, string $id, int $offset, string $chunkTempLocalPath): array
    {
        if ($offset < 0 || ! is_file($chunkTempLocalPath) || is_link($chunkTempLocalPath)) {
            $this->fail('json_chunk_invalid');
        }

        $temporary = realpath($chunkTempLocalPath);
        $allowed = false;

        foreach ([sys_get_temp_dir(), storage_path('app/private/json-import-request-chunks')] as $candidate) {
            $temporaryRoot = realpath($candidate);

            if (! is_link($candidate) && $temporary && $temporaryRoot && $this->within($temporary, $temporaryRoot)) {
                $allowed = true;
                break;
            }
        }

        if (! $allowed) {
            $this->fail('json_chunk_invalid');
        }

        return $this->locked($userId, $id, LOCK_EX, function (string $directory, array $meta) use ($offset, $temporary) {
            if ($meta['state'] !== 'uploading') {
                $this->fail('json_source_state', 409);
            }

            $length = filesize($temporary);
            $expected = min(self::CHUNK_BYTES, $meta['size'] - $offset);

            if (! is_int($length) || $length < 1 || $length !== $expected || $offset % self::CHUNK_BYTES !== 0) {
                $this->fail('json_chunk_invalid');
            }

            if ($offset > $meta['received']) {
                $this->fail('json_chunk_offset', 409);
            }

            $path = $directory.DIRECTORY_SEPARATOR.'upload.part';
            $receiptsPath = $directory.DIRECTORY_SEPARATOR.'chunks.sha256';
            $this->regular($path);
            $this->regular($receiptsPath);
            $source = @fopen($path, 'c+b');
            $receipts = @fopen($receiptsPath, 'c+b');
            $chunk = @fopen($temporary, 'rb');

            if (! is_resource($source) || ! is_resource($receipts) || ! is_resource($chunk)) {
                foreach ([$source, $receipts, $chunk] as $handle) {
                    if (is_resource($handle)) {
                        fclose($handle);
                    }
                }

                $this->fail('json_source_unavailable', 500);
            }

            try {
                $stat = fstat($source);

                if ($stat['size'] < $meta['received']) {
                    $this->fail('json_source_changed', 409);
                }

                if ($stat['size'] > $meta['received']) {
                    ftruncate($source, $meta['received']);
                }

                $chunkHash = hash_file('sha256', $temporary, true);

                if ($offset < $meta['received']) {
                    if ($offset + $length > $meta['received'] || fseek($source, $offset) !== 0) {
                        $this->fail('json_chunk_mismatch', 409);
                    }

                    $hash = hash_init('sha256');
                    $read = hash_update_stream($hash, $source, $length);

                    if ($read !== $length || ! hash_equals($chunkHash, hash_final($hash, true))) {
                        $this->fail('json_chunk_mismatch', 409);
                    }

                    return $this->publicInfo($meta);
                }

                $this->space($directory, $length);
                fseek($source, $offset);
                $copied = stream_copy_to_stream($chunk, $source, $length);

                if ($copied !== $length || ! fflush($source)) {
                    ftruncate($source, $meta['received']);
                    $this->fail('json_source_unavailable', 500);
                }

                fseek($receipts, intdiv($offset, self::CHUNK_BYTES) * 32);

                if (fwrite($receipts, $chunkHash) !== 32 || ! fflush($receipts)) {
                    ftruncate($source, $meta['received']);
                    $this->fail('json_source_unavailable', 500);
                }

                $meta['received'] += $length;
                $this->writeMeta($directory, $meta);

                return $this->publicInfo($meta);
            } finally {
                fclose($source);
                fclose($receipts);
                fclose($chunk);
            }
        });
    }

    public function info(int $userId, string $id, ?string $supplierCode = null): array
    {
        return $this->locked($userId, $id, LOCK_SH, function (string $directory, array $meta) use ($supplierCode) {
            $this->supplier($meta, $supplierCode);

            if ($meta['state'] === 'ready') {
                $this->unchanged($directory, $meta);
            }

            return $this->publicInfo($meta);
        });
    }

    protected function record(JsonImportSourceReader $reader): string
    {
        if ($reader->peek() !== '{') {
            $this->fail('json_source_invalid_json');
        }

        $record = '';
        $depth = 0;
        $quoted = false;

        for (; ;) {
            $this->piece($record, $reader->span($quoted ? '"\\' : '{}"'));
            $byte = $reader->byte();

            if ($byte === null) {
                $this->fail('json_source_invalid_json');
            }

            $this->piece($record, $byte);

            if ($quoted) {
                if ($byte === '\\') {
                    $escaped = $reader->byte();

                    if ($escaped === null) {
                        $this->fail('json_source_invalid_json');
                    }

                    $this->piece($record, $escaped);
                } elseif ($byte === '"') {
                    $quoted = false;
                }
            } elseif ($byte === '"') {
                $quoted = true;
            } elseif ($byte === '{') {
                $depth++;
            } elseif ($byte === '}') {
                $depth--;

                if ($depth === 0) {
                    return $record;
                }
            }
        }
    }

    protected function piece(string &$record, string $piece): void
    {
        if (strlen($record) + strlen($piece) > self::MAX_RECORD_BYTES) {
            $this->fail('json_record_too_large');
        }

        $record .= $piece;
    }

    protected function whitespace(JsonImportSourceReader $reader): void
    {
        while (($byte = $reader->peek()) !== null && str_contains(" \t\r\n", $byte)) {
            $reader->byte();
        }
    }

    protected function unchanged(string $directory, array $meta): void
    {
        $source = $directory.DIRECTORY_SEPARATOR.'source.json';
        $receipts = $directory.DIRECTORY_SEPARATOR.'chunks.sha256';
        $this->regular($source);
        $this->regular($receipts);
        clearstatcache(true, $source);
        $stat = stat($source);

        foreach ($meta['source_stat'] ?? [] as $key => $value) {
            if (($stat[$key] ?? null) !== $value) {
                $this->fail('json_source_changed', 409);
            }
        }

        if (! isset($meta['sha256'], $meta['receipts_sha256'])
            || ! hash_equals($meta['receipts_sha256'], hash_file('sha256', $receipts))) {
            $this->fail('json_source_changed', 409);
        }
    }

    protected function supplier(array $meta, ?string $code): void
    {
        if ($code !== null && $meta['supplier'] !== $code) {
            $this->fail('json_source_changed', 409);
        }
    }

    protected function locked(int $userId, string $id, int $mode, callable $callback): mixed
    {
        $directory = $this->directory($userId, $id);
        $path = $directory.DIRECTORY_SEPARATOR.'.lock';
        $this->regular($path);
        $handle = @fopen($path, 'r+b');

        if (! is_resource($handle) || ! flock($handle, $mode)) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            $this->fail('json_source_unavailable', 500);
        }

        try {
            return $callback($directory, $this->meta($directory, $userId, $id));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    protected function meta(string $directory, int $userId, string $id): array
    {
        $path = $directory.DIRECTORY_SEPARATOR.'meta.json';
        $this->regular($path);

        if (filesize($path) > 16384) {
            $this->fail('json_source_invalid');
        }

        $meta = json_decode(file_get_contents($path), true);

        if (! is_array($meta) || ($meta['id'] ?? null) !== $id || ($meta['user_id'] ?? null) !== $userId) {
            $this->fail('json_source_forbidden', 403);
        }

        if (! isset($meta['expires_at'], $meta['size'], $meta['received'], $meta['state'], $meta['supplier'])
            || ! is_int($meta['size']) || ! is_int($meta['received']) || $meta['received'] > $meta['size']) {
            $this->fail('json_source_invalid');
        }

        if ($meta['expires_at'] < now()->timestamp) {
            $this->fail('json_source_expired', 410);
        }

        return $meta;
    }

    protected function writeMeta(string $directory, array $meta): void
    {
        $path = $directory.DIRECTORY_SEPARATOR.'meta.json';

        if (is_link($path)) {
            $this->fail('json_source_invalid');
        }

        $temporary = $directory.DIRECTORY_SEPARATOR.'meta.'.bin2hex(random_bytes(8)).'.tmp';
        $handle = @fopen($temporary, 'x+b');

        if (! is_resource($handle)) {
            $this->fail('json_source_unavailable', 500);
        }

        try {
            $json = json_encode($meta, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

            if (fwrite($handle, $json) !== strlen($json) || ! fflush($handle)) {
                $this->fail('json_source_unavailable', 500);
            }
        } finally {
            fclose($handle);
        }

        @chmod($temporary, 0600);

        if (! @rename($temporary, $path)) {
            @unlink($temporary);
            $this->fail('json_source_unavailable', 500);
        }
    }

    protected function publicInfo(array $meta): array
    {
        return ['id' => $meta['id'], 'uploadId' => $meta['id'], 'token' => $meta['state'] === 'ready' ? $meta['id'] : null,
            'supplier' => $meta['supplier'], 'name' => $meta['name'], 'size' => $meta['size'], 'total' => $meta['size'],
            'received' => $meta['received'], 'nextOffset' => $meta['received'], 'chunkBytes' => self::CHUNK_BYTES,
            'state' => $meta['state'], 'sha256' => $meta['sha256'], 'expires_at' => $meta['expires_at']];
    }

    protected function directory(int $userId, string $id): string
    {
        if ($userId < 1 || ! Str::isUuid($id)) {
            $this->fail('json_source_invalid');
        }

        $base = $this->base();
        $parent = $base.DIRECTORY_SEPARATOR.$userId;
        $directory = $parent.DIRECTORY_SEPARATOR.$id;

        if (is_link($parent) || is_link($directory)) {
            $this->fail('json_source_invalid');
        }

        if (! is_dir($directory)) {
            $this->fail('json_source_not_found', 404);
        }

        return $directory;
    }

    protected function base(): string
    {
        $this->makeDirectory($this->root);
        $base = realpath($this->root);

        if (! $base) {
            $this->fail('json_source_unavailable', 500);
        }

        return $base;
    }

    protected function makeDirectory(string $path): void
    {
        if (is_link($path) || (! is_dir($path) && ! @mkdir($path, 0700, true))) {
            $this->fail('json_source_invalid');
        }
    }

    protected function regular(string $path): void
    {
        if (is_link($path) || ! is_file($path)) {
            $this->fail('json_source_changed', 409);
        }
    }

    protected function within(string $path, string $root): bool
    {
        $path = str_replace('\\', '/', $path);
        $root = rtrim(str_replace('\\', '/', $root), '/').'/';

        return DIRECTORY_SEPARATOR === '\\' ? str_starts_with(strtolower($path), strtolower($root)) : str_starts_with($path, $root);
    }

    protected function space(string $directory, int $bytes): void
    {
        $free = @disk_free_space($directory);

        if ($free !== false && $free < $bytes + 16777216) {
            $this->fail('json_source_no_space', 507);
        }
    }

    protected function fail(string $reason, int $status = 422): never
    {
        throw new JsonImportSourceException($reason, $status);
    }
}
