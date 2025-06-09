<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\FilterCacheService;

class CatalogController extends Controller
{
    protected FilterCacheService $filterCache;

    public function __construct(FilterCacheService $filterCache)
    {
        $this->filterCache = $filterCache;
    }

    /**
     * Get a paginated list of products with optional filtering and sorting.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function products(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 10);
        $offset = ($page - 1) * $limit;
        $sortBy = $request->input('sort_by');
        $filters = $request->input('filter', []);

        $productIds = $this->filterCache->getFilteredProductIds($filters);

        $total = count($productIds);

        if ($total === 0) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => $page,
                    'last_page' => 0,
                    'per_page' => $limit,
                    'total' => 0,
                ]
            ]);
        }

        $productIdsPage = array_slice($productIds, $offset, $limit);

        $query = DB::table('products')->whereIn('id', $productIdsPage);

        switch ($sortBy) {
            case 'price_asc':
                $query->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price', 'desc');
                break;
            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;
            default:
                $query->orderBy('id', 'asc');
                break;
        }

        $products = $query->get();

        return response()->json([
            'data' => $products,
            'meta' => [
                'current_page' => $page,
                'last_page' => ceil($total / $limit),
                'per_page' => $limit,
                'total' => $total,
            ]
        ]);
    }
}
