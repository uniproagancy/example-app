<?php

namespace App\Console\Commands;

use App\Models\Attribute;
use App\Services\Import\SpecNames;
use App\Support\Catalog;
use App\Support\Locales;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('attributes:translate-names {--dry-run : List the renames without saving them}')]
#[Description('Give attributes that arrived with English supplier names their Georgian name, keeping the English one')]
class TranslateAttributeNames extends Command
{
    public function handle(): int
    {
        $renamed = [];

        Attribute::query()->with('translations')->orderBy('id')->each(function (Attribute $attribute) use (&$renamed): void {
            $current = (string) $attribute->translate(Locales::default(), false)?->name;

            if ($current === '' || SpecNames::isGeorgian($current)) {
                return;
            }

            $names = SpecNames::translate($current);

            if ($names['ka'] === $current) {
                return;
            }

            $renamed[] = [$attribute->code, $current, $names['ka']];

            if (! $this->option('dry-run')) {
                $attribute->saveTranslations(array_filter([
                    Locales::default() => ['name' => $names['ka']],
                    'en' => blank($attribute->translate('en', false)?->name) ? ['name' => $names['en']] : null,
                ]));
            }
        });

        if ($renamed === []) {
            $this->info('Nothing to translate.');

            return self::SUCCESS;
        }

        $this->table(['code', 'was', 'now'], $renamed);

        if (! $this->option('dry-run')) {
            Catalog::flush();
        }

        $this->info(($this->option('dry-run') ? 'Would rename ' : 'Renamed ').count($renamed).' attribute(s).');

        return self::SUCCESS;
    }
}
