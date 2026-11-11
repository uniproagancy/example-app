<?php

namespace App\Models\Concerns;

use App\Support\Locales;
use App\Support\Text;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasTranslations
{
    public function translations(): HasMany
    {
        return $this->hasMany($this->translationModelClass(), $this->getForeignKey());
    }

    public function translationModelClass(): string
    {
        return static::class.'Translation';
    }

    public function translatableAttributes(): array
    {
        return $this->translatable ?? [];
    }

    public function translate(?string $locale = null, bool $fallback = true): ?Model
    {
        $locale ??= app()->getLocale();
        $row = $this->translations->firstWhere('locale', $locale);

        if (! $row && $fallback) {
            $row = $this->translations->firstWhere('locale', Locales::default());
        }

        return $row;
    }

    public function getAttribute($key)
    {
        if (is_string($key) && in_array($key, $this->translatableAttributes(), true)) {
            return $this->translatedValue($key);
        }

        return parent::getAttribute($key);
    }

    public function translatedValue(string $key, ?string $locale = null): mixed
    {
        $value = $this->translate($locale, false)?->getAttribute($key);

        if ($value === null || $value === '') {
            $value = $this->translate(Locales::default(), false)?->getAttribute($key);
        }

        return $value;
    }

    public function scopeWithTranslation(Builder $query, ?string $locale = null): Builder
    {
        $locales = array_values(array_unique([$locale ?? app()->getLocale(), Locales::default()]));

        return $query->with(['translations' => fn ($translations) => $translations->whereIn('locale', $locales)]);
    }

    public function saveTranslations(array $byLocale): void
    {
        $allowed = array_flip($this->translatableAttributes());

        foreach ($byLocale as $locale => $fields) {
            $fields = Text::utf8All(array_intersect_key((array) $fields, $allowed));

            if (! array_filter($fields, fn ($value) => $value !== null && $value !== '')) {
                continue;
            }

            $this->translations()->updateOrCreate(['locale' => $locale], $fields);
        }

        $this->unsetRelation('translations');
    }
}
