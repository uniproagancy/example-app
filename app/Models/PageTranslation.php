<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

#[Fillable(['page_id', 'locale', 'title', 'intro', 'body', 'meta_description'])]
class PageTranslation extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(Page::CACHE_KEY));
        static::deleted(fn () => Cache::forget(Page::CACHE_KEY));
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
