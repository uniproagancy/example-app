<?php

namespace App\Http\Controllers;

use App\Support\Catalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

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
                ->map(fn (array $product) => [
                    ...collect($product)->only(['id', 'image', 'brand', 'name', 'subLabel', 'priceLabel'])->all(),
                    'url' => LaravelLocalization::getLocalizedURL(app()->getLocale(), $product['url'], [], true) ?: $product['url'],
                ])
                ->values()
                ->all(),
            'total' => $found->count(),
        ]);
    }
}
