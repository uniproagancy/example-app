<?php

namespace App\Support;

class Measure
{
    public const UNITS = [
        'length' => [
            'mm' => ['factor' => 1, 'words' => ['mm', 'მმ']],
            'cm' => ['factor' => 10, 'words' => ['cm', 'სმ', 'სანტიმეტრი']],
            'm' => ['factor' => 1000, 'words' => ['m', 'მ', 'მეტრი']],
            'inch' => ['factor' => 25.4, 'words' => ['"', "''", '”', '″', 'inch', 'inches', 'in', 'ინჩი', 'ინჩ']],
        ],
        'volume' => [
            'ml' => ['factor' => 1, 'words' => ['ml', 'მლ']],
            'l' => ['factor' => 1000, 'words' => ['l', 'ლ', 'ლიტრი', 'ლტ', 'liter', 'litre', 'liters', 'litres']],
        ],
        'weight' => [
            'g' => ['factor' => 1, 'words' => ['g', 'gr', 'გრ', 'გ', 'გრამი']],
            'kg' => ['factor' => 1000, 'words' => ['kg', 'კგ', 'კილოგრამი']],
        ],
        'power' => [
            'w' => ['factor' => 1, 'words' => ['w', 'watt', 'watts', 'ვტ', 'ვატი', 'ვატ']],
            'kw' => ['factor' => 1000, 'words' => ['kw', 'კვტ', 'კილოვატი']],
        ],
        'heat' => [
            'btu' => ['factor' => 1, 'words' => ['btu']],
        ],
        'memory' => [
            'mb' => ['factor' => 1, 'words' => ['mb', 'მბ']],
            'gb' => ['factor' => 1024, 'words' => ['gb', 'გბ']],
            'tb' => ['factor' => 1048576, 'words' => ['tb', 'ტბ']],
        ],
        'area' => [
            'sqm' => ['factor' => 1, 'words' => ['m²', 'მ²', 'm2', 'მ2', 'sqm', 'კვ.მ', 'კვმ']],
        ],
        'frequency' => [
            'hz' => ['factor' => 1, 'words' => ['hz', 'ჰც', 'ჰერცი']],
            'khz' => ['factor' => 1000, 'words' => ['khz', 'კჰც']],
        ],
        'voltage' => [
            'v' => ['factor' => 1, 'words' => ['v', 'ვ', 'ვოლტი', 'volt', 'volts']],
        ],
        'sound' => [
            'db' => ['factor' => 1, 'words' => ['db', 'დბ', 'დეციბელი']],
        ],

        'time' => [
            'month' => ['factor' => 1, 'words' => ['თვე', 'თვის', 'month', 'months', 'mo']],
            'year' => ['factor' => 12, 'words' => ['წელი', 'წლის', 'წელიწადი', 'year', 'years', 'yr']],
        ],
    ];

    protected const FIXED = ['memory'];

    protected const SNAP = 0.15;

    protected const CONTEXT = 0.5;

    protected const LEEWAY = 3;

    protected const NOT_IN_NAMES = ['g', 'in'];

    protected const LADDER = [
        0.1, 0.25, 0.5, 1, 2, 5, 10, 20, 25, 50,
        100, 200, 250, 500, 1000, 2000, 5000, 10000, 20000, 50000,
        100000, 200000, 500000, 1000000,
    ];

    protected static ?array $index = null;

    public static function read(?string $label): ?array
    {
        $label = Text::tidy($label);

        if ($label === '') {
            return null;
        }

        if ($reading = self::whole($label)) {
            return $reading;
        }

        if (preg_match('/\(([^)]+)\)/u', $label, $inside)) {
            if ($reading = self::whole(trim($inside[1]))) {
                return $reading;
            }
        }

        return self::leading($label);
    }

    protected static function whole(string $label): ?array
    {
        $units = self::pattern();

        $matched = preg_match(
            '/^(\d+(?:[.,]\d+)?)\s*(?:(?:-|–|—|\/|to)\s*(\d+(?:[.,]\d+)?))?\s*('.$units.')(?:\s*(?:'.$units.'))*$/u',
            $label,
            $parts,
        );

        return $matched ? self::reading($parts[3], $parts[1], $parts[2] ?? '') : null;
    }

    protected static function leading(string $label): ?array
    {
        $matched = preg_match('/(\d+(?:[.,]\d+)?)\s*('.self::pattern().')(?![\p{L}\p{N}])/u', $label, $parts);

        if (! $matched) {
            return null;
        }

        $reading = self::reading($parts[2], $parts[1], '');

        return $reading && $reading['dimension'] === 'memory' ? $reading : null;
    }

    protected static function reading(string $word, string $low, string $high): ?array
    {
        [$dimension, $unit] = self::index()[$word];
        $factor = self::UNITS[$dimension][$unit]['factor'];

        $from = self::figure($low) * $factor;
        $to = $high === '' ? null : self::figure($high) * $factor;

        if ($to !== null && $to <= $from) {
            return null;
        }

        return [
            'dimension' => $dimension,
            'unit' => $unit,
            'low' => $from,
            'high' => $to,
        ];
    }

    protected static function pattern(): string
    {
        return implode('|', array_map(
            fn (string $word) => preg_quote($word, '/'),
            array_keys(self::index()),
        ));
    }

    public static function prevailing(array $readings, array $held = []): array
    {
        $weight = [];

        foreach ($readings as $code => $reading) {
            if (! $reading || in_array($reading['dimension'], self::FIXED, true)) {
                continue;
            }

            $weight[$reading['dimension']][$reading['unit']] =
                ($weight[$reading['dimension']][$reading['unit']] ?? 0)
                + max(1, (int) ($held[$code] ?? 0));
        }

        return array_map(function (array $units) {
            arsort($units);

            return (string) array_key_first($units);
        }, $weight);
    }

    public static function canonical(array $reading, ?string $unit = null): string
    {
        $unit ??= $reading['unit'];

        if (! isset(self::UNITS[$reading['dimension']][$unit])
            || in_array($reading['dimension'], self::FIXED, true)) {
            $unit = $reading['unit'];
        }

        $factor = self::UNITS[$reading['dimension']][$unit]['factor'];

        $figure = self::write($reading['low'] / $factor)
            .($reading['high'] !== null ? '-'.self::write($reading['high'] / $factor) : '');

        $written = __("unit.{$unit}");

        return str_starts_with($written, '"') || $written === '"'
            ? $figure.$written
            : $figure.' '.$written;
    }

    public static function mentioned(?string $text): array
    {

        preg_match_all(
            '/(?<![\p{L}\p{N}.,\/])(\d+(?:[.,]\d+)?)(?:\s*[-–]\s*(\d+(?:[.,]\d+)?))?\s*('.self::pattern().')(?![\p{L}\p{N}])/u',
            Text::tidy($text),
            $found,
            PREG_SET_ORDER,
        );

        $readings = [];

        foreach ($found as [, $low, $high, $word]) {
            if (in_array($word, self::NOT_IN_NAMES, true)) {
                continue;
            }

            if ($reading = self::reading($word, $low, $high)) {
                $readings[] = $reading;
            }
        }

        return $readings;
    }

    public static function understood(array $readings, array $units, array $labels): array
    {
        $known = array_filter($readings);

        if (count($known) < max(2, count($labels) * self::CONTEXT)) {
            return $readings;
        }

        $quantities = array_unique(array_column($known, 'dimension'));

        if (count($quantities) !== 1) {
            return $readings;
        }

        $dimension = (string) reset($quantities);
        $unit = $units[$dimension] ?? null;

        if ($unit === null || ! isset(self::UNITS[$dimension][$unit])) {
            return $readings;
        }

        $sizes = array_column($known, 'low');
        $low = min($sizes) / self::LEEWAY;
        $high = max($sizes) * self::LEEWAY;
        $factor = self::factor($dimension, $unit);

        foreach ($readings as $code => $reading) {
            if ($reading !== null) {
                continue;
            }

            $said = Text::tidy($labels[$code] ?? null);

            if (! preg_match('/^(\d+(?:[.,]\d+)?)$/u', $said, $figure)) {
                continue;
            }

            $size = self::figure($figure[1]) * $factor;

            if ($size >= $low && $size <= $high) {
                $readings[$code] = ['dimension' => $dimension, 'unit' => $unit, 'low' => $size, 'high' => null];
            }
        }

        return $readings;
    }

    public static function bandable(string $dimension): bool
    {
        return ! in_array($dimension, self::FIXED, true);
    }

    public static function factor(string $dimension, string $unit): float
    {
        return (float) (self::UNITS[$dimension][$unit]['factor'] ?? 1);
    }

    public static function step(float $low, float $high, int $most): float
    {
        foreach (self::LADDER as $step) {
            if (ceil($high / $step) - floor($low / $step) <= $most) {
                return $step;
            }
        }

        return (float) self::LADDER[count(self::LADDER) - 1];
    }

    public static function span(float $from, float $to, string $dimension, string $unit): string
    {
        $factor = self::factor($dimension, $unit);

        return self::canonical([
            'dimension' => $dimension,
            'unit' => $unit,
            'low' => $from * $factor,
            'high' => $to * $factor,
        ], $unit);
    }

    protected static function write(float $value): string
    {

        $value = $value < 1 ? round($value, 2) : ($value < 100 ? round($value, 1) : round($value));

        $whole = round($value);

        if (abs($value - $whole) <= self::SNAP) {
            $value = $whole;
        }

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    protected static function figure(string $written): float
    {
        return (float) str_replace(',', '.', $written);
    }

    protected static function index(): array
    {
        if (self::$index !== null) {
            return self::$index;
        }

        $index = [];

        foreach (self::UNITS as $dimension => $units) {
            foreach ($units as $unit => $about) {
                foreach ($about['words'] as $word) {
                    $index[$word] ??= [$dimension, $unit];
                }
            }
        }

        uksort($index, fn ($a, $b) => mb_strlen((string) $b) <=> mb_strlen((string) $a));

        self::$index = $index;

        return $index;
    }
}
