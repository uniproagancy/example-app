<?php

namespace App\Http\Controllers;

use App\Models\ProductOffer;
use App\Models\Supplier;
use App\Models\SupplierStock;
use App\Services\Import\Drivers\Alta\AltaDriver;
use App\Services\Import\Drivers\Alta\AltaStockSynchronizer;
use App\Services\Import\Drivers\Alta\AltaUnavailable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AltaBrowserController extends Controller
{
    public const MAX_AGE_HOURS = 26;

    public function feed(Request $request, AltaStockSynchronizer $synchronizer): JsonResponse
    {
        abort_unless($request->user(), 401);
        abort_unless($request->user()->is_admin === true, 403);

        $supplier = Supplier::query()->where('code', 'alta')->where('is_active', true)->first();

        if (! $supplier || $supplier->driver !== AltaDriver::class) {
            return $this->unavailable('supplier_unavailable');
        }

        $stocks = $this->stocks($supplier);

        if ($this->stale($stocks)) {
            try {
                $synchronizer->sync($supplier);
            } catch (AltaUnavailable) {
                return $this->unavailable($stocks->isEmpty() ? 'stock_snapshot_unavailable' : 'stock_snapshot_stale');
            }

            $stocks = $this->stocks($supplier);

            if ($this->stale($stocks)) {
                return $this->unavailable('stock_snapshot_unavailable');
            }
        }

        $minimum = max(1, (int) ($supplier->config['min_quantity'] ?? 2));
        $held = ProductOffer::query()->where('supplier_id', $supplier->id)->whereHas('product')->pluck('external_id')->flip();

        $items = $stocks
            ->filter(fn (SupplierStock $stock) => $stock->quantity >= $minimum && preg_match('/\A[A-Za-z0-9_-]{1,64}\z/D', $stock->external_id))
            ->map(fn (SupplierStock $stock) => [
                'item' => $stock->external_id,
                'quantity' => (int) $stock->quantity,
                'on_site' => isset($held[$stock->external_id]),
            ])
            ->values();

        return response()->json([
            'schema' => 'elio.supplier-stock.v1',
            'supplier' => 'alta',
            'synced_at' => $stocks->min('synced_at')?->toIso8601String(),
            'count' => $items->count(),
            'minimum_quantity' => $minimum,
            'items' => $items,
        ], 200, ['Cache-Control' => 'private, no-store']);
    }

    /**
     * @return Collection<int, SupplierStock>
     */
    protected function stocks(Supplier $supplier): Collection
    {
        return SupplierStock::query()
            ->where('supplier_id', $supplier->id)
            ->orderBy('external_id')
            ->get(['external_id', 'quantity', 'synced_at']);
    }

    /**
     * @param  Collection<int, SupplierStock>  $stocks
     */
    protected function stale(Collection $stocks): bool
    {
        $oldest = $stocks->min('synced_at');

        return $oldest === null || $oldest->lt(now()->subHours(self::MAX_AGE_HOURS));
    }

    protected function unavailable(string $error): JsonResponse
    {
        return response()->json(['error' => $error], 503, ['Cache-Control' => 'private, no-store']);
    }
}
