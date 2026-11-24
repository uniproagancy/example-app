<?php

namespace App\Http\Controllers;

use App\Support\Catalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchSuggestController extends Controller
{
    public const SHOWN = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $query = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        if ($query === '') {
            return response()->json(['products' => [], 'total' => 0]);
        }

        $found = Catalog::search(Catalog::findMany(Catalog::searchCandidates($query)), $query);

        return response()->json([
            'products' => $found->take(self::SHOWN)
                ->map(fn (array $product) => collect($product)->only(['id', 'url', 'image', 'brand', 'name', 'subLabel', 'priceLabel'])->all())
                ->values()
                ->all(),
            'total' => $found->count(),
        ]);
    }
}
