<?php

namespace App\Services\Import;

use App\Models\Supplier;
use App\Models\SupplierStock;
use App\Support\Figure;
use App\Support\XlsxReader;
use Generator;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

class PriceListLoader
{
    public const MODE_REPLACE = 'replace';

    public const MODE_MERGE = 'merge';

    public const MODES = [self::MODE_REPLACE, self::MODE_MERGE];

    public const FORMATS = ['xlsx', 'xls', 'ods', 'csv', 'txt'];

    protected const FIGURES = ['cost', 'discount_price', 'old_price', 'sale'];

    public function load(
        Supplier $supplier,
        string $path,
        int $sheet = 1,
        int $skipRows = 1,
        string $mode = self::MODE_REPLACE,
        ?string $format = null,
    ): array {
        if (! in_array($mode, self::MODES, true)) {
            throw new RuntimeException("Unknown load mode [{$mode}].");
        }

        $map = $this->columns($supplier);
        $now = now();
        $rows = [];
        $duplicates = 0;

        foreach ($this->entries($supplier, $map, $path, $sheet, $skipRows, $format) as $entry) {
            if (isset($rows[$entry['external_id']])) {
                $duplicates++;
            }

            $rows[$entry['external_id']] = [
                'supplier_id' => $supplier->id,
                'external_id' => $entry['external_id'],
                'quantity' => $entry['quantity'],
                'cost_price' => $entry['cost_price'],
                'data' => json_encode($entry['data'], JSON_UNESCAPED_UNICODE),
                'synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! $rows) {
            return [
                'read' => 0,
                'zeroed' => 0,
                'duplicates' => 0,
                'missing_url' => 0,
                'no_stock_column' => empty($map['stock']),
                'mode' => $mode,
                'codes' => [],
            ];
        }

        foreach (array_chunk(array_values($rows), 500) as $chunk) {
            SupplierStock::upsert(
                $chunk,
                ['supplier_id', 'external_id'],
                ['quantity', 'cost_price', 'data', 'synced_at', 'updated_at'],
            );
        }

        $codes = array_map('strval', array_keys($rows));

        $zeroed = $mode === self::MODE_REPLACE
            ? SupplierStock::where('supplier_id', $supplier->id)
                ->whereNotIn('external_id', $codes)
                ->where('quantity', '>', 0)
                ->update(['quantity' => 0, 'synced_at' => $now])
            : 0;

        return [
            'read' => count($rows),
            'zeroed' => $zeroed,
            'duplicates' => $duplicates,
            'missing_url' => $this->withoutLink($supplier, $map, $now),
            'no_stock_column' => empty($map['stock']),
            'mode' => $mode,
            'codes' => $codes,
        ];
    }

    public function preview(
        Supplier $supplier,
        string $path,
        int $sheet = 1,
        int $skipRows = 1,
        ?string $format = null,
        int $limit = 5,
    ): array {
        $rows = [];
        $total = 0;

        foreach ($this->entries($supplier, $this->columns($supplier), $path, $sheet, $skipRows, $format) as $entry) {
            $total++;

            if (count($rows) < $limit) {
                $rows[] = $entry;
            }
        }

        return ['rows' => $rows, 'total' => $total];
    }

    protected function columns(Supplier $supplier): array
    {
        $map = $supplier->config['columns'] ?? null;

        if (! is_array($map) || empty($map['key'])) {
            throw new RuntimeException("No column map on supplier [{$supplier->code}]. Add config.columns.");
        }

        return $map;
    }

    protected function entries(Supplier $supplier, array $map, string $path, int $sheet, int $skipRows, ?string $format): Generator
    {
        if (! is_file($path)) {
            throw new RuntimeException("File not found: {$path}");
        }

        foreach ($this->read($path, max(1, $sheet), $format) as $i => $cells) {
            if ($i < $skipRows) {
                continue;
            }

            $key = $this->keyFor($cells, $map);

            if ($key === '') {
                continue;
            }

            yield [
                'external_id' => $key,
                'quantity' => $this->quantity($cells, $map),
                'cost_price' => $this->money($cells[$map['cost'] ?? ''] ?? null, $supplier),
                'data' => $this->pick($cells, $map, $supplier),
            ];
        }
    }

    protected function withoutLink(Supplier $supplier, array $map, $now): int
    {
        if (empty($map['url'])) {
            return 0;
        }

        return SupplierStock::where('supplier_id', $supplier->id)
            ->where('synced_at', $now)
            ->whereRaw('json_extract(data, "$.url") not like "http%"')
            ->count();
    }

    protected function read(string $path, int $sheet, ?string $format = null): iterable
    {
        $format = strtolower($format ?: pathinfo($path, PATHINFO_EXTENSION));

        return match ($format) {
            'csv', 'txt' => $this->csv($path),
            'xls', 'ods' => $this->spreadsheet($path, $sheet),
            default => (new XlsxReader($path))->rows($sheet),
        };
    }

    protected function csv(string $path): iterable
    {
        $separator = $this->guessSeparator($path);
        $handle = fopen($path, 'r');
        $first = true;

        while (($data = fgetcsv($handle, 0, $separator, '"', '')) !== false) {
            $cells = [];

            foreach (array_values($data) as $i => $value) {
                $value = trim((string) $value);

                if ($first && $i === 0) {
                    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
                }

                $cells[$this->columnName($i)] = $value;
            }

            $first = false;

            if (array_filter($cells, fn (string $value) => $value !== '')) {
                yield $cells;
            }
        }

        fclose($handle);
    }

    protected function spreadsheet(string $path, int $sheet): iterable
    {
        try {
            $book = IOFactory::load($path);
        } catch (Throwable) {
            throw new RuntimeException('That file is not a workbook.');
        }

        if ($sheet > $book->getSheetCount()) {
            throw new RuntimeException("Sheet {$sheet} is missing from the workbook.");
        }

        foreach ($book->getSheet($sheet - 1)->getRowIterator() as $row) {
            $cells = [];
            $links = [];
            $numbers = [];
            $iterator = $row->getCellIterator();
            $iterator->setIterateOnlyExistingCells(true);

            foreach ($iterator as $cell) {
                $column = $cell->getColumn();
                $cells[$column] = trim((string) $cell->getFormattedValue());

                if ($cell->hasHyperlink() && $cell->getHyperlink()->getUrl() !== '') {
                    $links[$column] = $cell->getHyperlink()->getUrl();
                }

                if ($cell->getDataType() === DataType::TYPE_NUMERIC && $cell->getStyle()->getNumberFormat()->getFormatCode() === 'General') {
                    $numbers[$column] = true;
                }
            }

            if ($links) {
                $cells['_links'] = $links;
            }

            if ($numbers) {
                $cells['_numbers'] = $numbers;
            }

            if (array_filter($cells, fn ($value) => $value !== '' && $value !== [])) {
                yield $cells;
            }
        }

        $book->disconnectWorksheets();
    }

    protected function columnName(int $index): string
    {
        $name = '';

        for ($i = $index; $i >= 0; $i = intdiv($i, 26) - 1) {
            $name = chr(65 + $i % 26).$name;
        }

        return $name;
    }

    protected function guessSeparator(string $path): string
    {
        $handle = fopen($path, 'r');
        $first = (string) fgets($handle);
        fclose($handle);

        $counts = [',' => substr_count($first, ','), ';' => substr_count($first, ';'), "\t" => substr_count($first, "\t")];
        arsort($counts);

        return reset($counts) > 0 ? array_key_first($counts) : ',';
    }

    protected function keyFor(array $cells, array $map): string
    {
        $key = $this->keyFrom($cells, (string) ($map['key'] ?? ''), $map['key_as'] ?? null);

        if ($key === '' && ! empty($map['key_else'])) {
            $key = $this->keyFrom($cells, (string) $map['key_else'], 'url');
        }

        return $key;
    }

    protected function keyFrom(array $cells, string $column, ?string $as): string
    {
        if ($as !== 'url') {
            return trim((string) ($cells[$column] ?? ''));
        }

        $raw = trim((string) ($cells['_links'][$column] ?? $cells[$column] ?? ''));

        if (! preg_match('~^https?://~i', $raw)) {
            return '';
        }

        parse_str((string) parse_url($raw, PHP_URL_QUERY), $query);

        if ($id = trim((string) ($query['product_id'] ?? ''))) {
            return $id;
        }

        $path = trim((string) parse_url($raw, PHP_URL_PATH), '/');

        if ($path !== '' && ctype_digit($path)) {
            return $path;
        }

        return substr(sha1(rtrim(strtolower($raw), '/')), 0, 32);
    }

    protected function pick(array $cells, array $map, ?Supplier $supplier = null): array
    {
        $picked = [];
        $links = $cells['_links'] ?? [];

        foreach ($map as $role => $column) {
            if (in_array($role, ['key', 'key_as', 'key_else'], true) || ! is_string($column)) {
                continue;
            }

            $value = $role === 'url'
                ? trim((string) ($links[$column] ?? $cells[$column] ?? ''))
                : trim((string) ($cells[$column] ?? ''));

            if ($value === '') {
                continue;
            }

            $picked[$role] = in_array($role, self::FIGURES, true)
                ? $this->money($value, $supplier)
                : $value;
        }

        if (isset($cells['_numbers'][$map['key'] ?? ''])) {
            $picked['key_as_number'] = true;
        }

        return array_filter($picked, fn ($value) => $value !== null);
    }

    protected function quantity(array $cells, array $map): int
    {
        if (empty($map['stock'])) {
            return 1;
        }

        $text = trim((string) ($cells[$map['stock']] ?? ''));

        if ($text === '') {
            return 1;
        }

        if (is_numeric($text)) {
            return max(0, (int) floor((float) $text));
        }

        $digits = preg_replace('/\D+/', '', $text);

        return $digits === '' ? 0 : min(PHP_INT_MAX, (int) $digits);
    }

    protected function money(?string $text, ?Supplier $supplier = null): ?float
    {
        return Figure::money($text, $supplier?->config['decimal'] ?? null);
    }
}
