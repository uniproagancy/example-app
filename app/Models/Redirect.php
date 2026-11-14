<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

#[Fillable(['from_path', 'to_url', 'status_code', 'is_active', 'is_automatic'])]
class Redirect extends Model
{
    use LogsActivity;

    public const CACHE_KEY = 'redirects.active';

    public const CODES = [301, 302];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'is_active' => 'boolean',
            'is_automatic' => 'boolean',
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Redirect $redirect): void {
            $redirect->from_path = static::normalize($redirect->from_path);
        });

        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public static function normalize(?string $path): string
    {
        $path = (string) parse_url(trim((string) $path), PHP_URL_PATH);
        $path = '/'.trim(rawurldecode($path), '/');

        return Str::lower($path);
    }

    public static function matching(string $path): ?self
    {
        try {
            $map = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()
                ->where('is_active', true)
                ->pluck('id', 'from_path')
                ->all());
        } catch (QueryException) {
            return null;
        }

        $id = $map[static::normalize($path)] ?? null;

        return $id ? static::query()->find($id) : null;
    }

    public static function record(string $from, string $to): void
    {
        $from = static::normalize($from);
        $to = static::normalize($to);

        if ($from === $to) {
            return;
        }

        static::query()->where('from_path', $to)->where('is_automatic', true)->delete();

        static::query()->where('is_automatic', true)->where('to_url', $from)->update(['to_url' => $to]);

        static::query()->updateOrCreate(
            ['from_path' => $from],
            ['to_url' => $to, 'status_code' => 301, 'is_active' => true, 'is_automatic' => true],
        );

        Cache::forget(self::CACHE_KEY);
    }

    public function target(): string
    {
        return Str::startsWith($this->to_url, ['http://', 'https://']) ? $this->to_url : url($this->to_url);
    }

    public function registerHit(): void
    {
        static::query()->whereKey($this->id)->update([
            'hits' => $this->hits + 1,
            'last_hit_at' => now(),
        ]);
    }
}
