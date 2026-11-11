<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait GeneratesUniqueSlug
{
    abstract protected function slugSource(): string;

    protected static function bootGeneratesUniqueSlug(): void
    {
        static::saving(function (self $translation): void {
            $translation->slug = $translation->uniqueSlug(filled($translation->slug) ? $translation->slug : $translation->slugSource());
        });
    }

    protected function uniqueSlug(string $value): string
    {
        $base = Str::slug($value, '-', $this->locale) ?: Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 2;

        while (
            static::query()
                ->where('locale', $this->locale)
                ->where('slug', $slug)
                ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
