<?php

namespace App\Services\Import\Concerns;

use App\Models\SupplierStock;

trait RecordsSkips
{
    protected function note(SupplierStock $row, ?string $reason): void
    {
        $data = (array) ($row->data ?? []);

        if (($data['skipped'] ?? null) === $reason) {
            return;
        }

        $data['skipped'] = $reason;

        $row->forceFill(['data' => array_filter($data, fn ($value) => $value !== null)])->saveQuietly();
    }
}
