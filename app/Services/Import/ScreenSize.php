<?php

namespace App\Services\Import;

use App\Support\Measure;

class ScreenSize
{
    public const ATTRIBUTE_CODE = 'ekranis-zoma';

    public static function attributeCodes(): array
    {
        return [self::ATTRIBUTE_CODE, 'zoma', 'ekranis-diagonali', 'screen'];
    }

    public static function matches(?int $categoryId, string $name, array $rows): bool
    {
        $name = preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($name));

        return in_array($categoryId, ImportRules::categoryIds('screen_size'), true)
            && in_array($name, ['ზომა', 'ეკრანისზომა', 'ეკრანისდიაგონალი', 'დიაგონალი', 'ეკრანი', 'screensize', 'screendiagonal', 'diagonal', 'screen'], true)
            && self::normalizeRows($rows) !== [];
    }

    public static function normalize(string $value): ?string
    {
        if (mb_strlen($value) > 128 || preg_match('/[<>\x00-\x1F\x7F]/u', $value)) {
            return null;
        }

        $reading = Measure::read($value);

        if ($reading === null || $reading['dimension'] !== 'length'
            || $reading['low'] <= 0 || $reading['high'] !== null) {
            return null;
        }

        return Measure::canonical($reading, 'inch');
    }

    public static function normalizeRows(array $rows): array
    {
        $normalized = [];
        $quantities = [];

        foreach ($rows as $row) {
            $label = self::normalize((string) ($row['value'] ?? ''));

            if ($label !== null) {
                $quantities[$label] = true;
                $row['value'] = $label;
                $normalized[] = $row;
            }
        }

        return count($quantities) === 1 ? $normalized : [];
    }
}
