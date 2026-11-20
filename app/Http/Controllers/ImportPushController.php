<?php

namespace App\Http\Controllers;

use App\Models\ProductOffer;
use App\Models\Supplier;
use App\Services\Import\Drivers\Zoommer\ZoommerDriver;
use App\Services\Import\ImageDownloader;
use App\Services\Import\ProductImporter;
use App\Services\Import\ProductPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ImportPushController extends Controller
{
    public const DRIVERS = [ZoommerDriver::class];

    protected const SHARED_CONFIG = ['from', 'to', 'full_scan', 'discover_ahead', 'locales', 'stock_cities', 'stock_units'];

    public function show(Request $request, string $supplier): JsonResponse
    {
        $supplier = $this->supplier($request, $supplier);

        return response()->json([
            'code' => $supplier->code,
            'driver' => $supplier->driver,
            'config' => array_intersect_key($supplier->config ?? [], array_flip(self::SHARED_CONFIG)),
            'known_ids' => ProductOffer::query()->where('supplier_id', $supplier->id)->pluck('external_id')->map(fn ($id) => (string) $id)->all(),
        ]);
    }

    public function store(Request $request, string $supplier, ProductImporter $importer): JsonResponse
    {
        $supplier = $this->supplier($request, $supplier);
        $payload = $this->payload((string) $request->input('payload'));
        $files = array_filter((array) $request->file('images', []), fn ($file) => $file instanceof UploadedFile && $file->isValid());

        if ($files === [] && $this->needsImages($supplier, $payload)) {
            return response()->json(['status' => 'needs_images']);
        }

        $bytes = [];

        foreach ($files as $index => $file) {
            $url = $payload->images[(int) $index] ?? null;

            if ($url !== null && $file->getSize() <= (int) config('import.max_image_bytes')) {
                $bytes[$url] = $file->get();
            }
        }

        $product = ImageDownloader::using($bytes, fn () => $importer->import($supplier, $payload));

        Log::channel('import')->info('pushed product saved', [
            'supplier' => $supplier->code,
            'external' => $payload->externalId,
            'product' => $product?->id,
            'images_given' => count($bytes),
        ]);

        return response()->json([
            'status' => $product ? 'saved' : 'skipped',
            'product' => $product?->id,
            'images' => $product?->images()->count() ?? 0,
        ]);
    }

    protected function supplier(Request $request, string $code): Supplier
    {
        $token = (string) config('import.push_token');

        abort_if(strlen($token) < 32, 404);
        abort_unless(hash_equals($token, (string) $request->bearerToken()), 401);

        $supplier = Supplier::query()->where('code', $code)->where('is_active', true)->first();

        abort_unless($supplier && in_array($supplier->driver, self::DRIVERS, true), 404);

        return $supplier;
    }

    protected function needsImages(Supplier $supplier, ProductPayload $payload): bool
    {
        $wanted = min(count($payload->images), ImageDownloader::MAX_IMAGES);

        if ($wanted === 0) {
            return false;
        }

        $offer = ProductOffer::query()
            ->where('supplier_id', $supplier->id)
            ->where('external_id', $payload->externalId)
            ->with('product')
            ->first();

        return ! $offer?->product || $offer->product->images()->count() < $wanted;
    }

    protected function payload(string $json): ProductPayload
    {
        $data = json_decode($json, true);

        $validated = Validator::make(is_array($data) ? $data : [], [
            'externalId' => ['required', 'string', 'max:64'],
            'sku' => ['required', 'string', 'max:191'],
            'costPrice' => ['required', 'numeric', 'min:0'],
            'oldCostPrice' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'brandName' => ['nullable', 'string', 'max:191'],
            'categoryName' => ['nullable', 'string', 'max:500'],
            'translations' => ['required', 'array'],
            'specs' => ['array'],
            'images' => ['array', 'max:50'],
            'images.*' => ['string', 'url:https,http', 'max:2048'],
            'weight' => ['nullable', 'integer', 'min:0'],
            'variantGroup' => ['nullable', 'string', 'max:191'],
            'isPreorder' => ['boolean'],
            'releaseDate' => ['nullable', 'string', 'max:64'],
        ])->validate();

        return new ProductPayload(
            externalId: $validated['externalId'],
            sku: $validated['sku'],
            costPrice: (float) $validated['costPrice'],
            oldCostPrice: isset($validated['oldCostPrice']) ? (float) $validated['oldCostPrice'] : null,
            stock: (int) $validated['stock'],
            brandName: $validated['brandName'] ?? null,
            categoryName: $validated['categoryName'] ?? null,
            translations: $validated['translations'],
            specs: $validated['specs'] ?? [],
            images: array_values($validated['images'] ?? []),
            weight: $validated['weight'] ?? null,
            variantGroup: $validated['variantGroup'] ?? null,
            isPreorder: (bool) ($validated['isPreorder'] ?? false),
            releaseDate: $validated['releaseDate'] ?? null,
        );
    }
}
