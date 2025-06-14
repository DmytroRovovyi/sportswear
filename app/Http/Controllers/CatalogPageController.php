<?php

namespace App\Http\Controllers;

use App\Services\FilterCacheService;
use App\Traits\SortsFilterItems;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogPageController extends Controller
{
    use SortsFilterItems;
    protected FilterCacheService $filterCache;

    /**
     * Constructor
     */
    public function __construct(FilterCacheService $filterCacheService)
    {
        $this->filterCache = $filterCacheService;
    }

    /**
     * Get the catalog page with initial products and filters.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Foundation\Application
     */
    public function index(Request $request)
    {
        $allowedFilters = ['color', 'appointment', 'gender'];
        $filters = [];

        foreach ($allowedFilters as $filterKey) {
            $values = $request->input($filterKey, []);
            if (!empty($values)) {
                $filters[$filterKey] = $values;
            }
        }

        // Get product ids from the Redis service.
        $productIds = $this->filterCache->getFilteredProductIds($filters);

        $page = max((int)$request->get('page', 1), 1);
        $perPage = 10;
        $offset = ($page - 1) * $perPage;

        $pagedProductIds = array_slice($productIds, $offset, $perPage);

        $products = DB::table('offers')
            ->join('products', 'offers.product_id', '=', 'products.id')
            ->whereIn('offers.product_id', $pagedProductIds)
            ->select('offers.offer_id', 'offers.category_id', 'offers.vendor',
                'products.name', 'products.price', 'products.description')
            ->get();

        $filtersData = $this->getFiltersData($filters);
        $total = count($productIds);

        return view('index', [
            'products' => $products,
            'filters' => $filtersData,
            'currentPage' => $page,
            'lastPage' => (int) ceil($total / $perPage),
            'total' => $total,
        ]);
    }

    /**
     * Get method for obtaining filters with product counts.
     *
     * @param array $selectedFilters
     * @return array
     */
    protected function getFiltersData(array $selectedFilters): array
    {
        $result = [];

        $paramSlugs = ['brand', 'color', 'gender', 'appointment'];

        $parameters = DB::table('parameters')
            ->whereIn('slug', $paramSlugs)
            ->pluck('id', 'slug');

        $activeKeys = [];

        foreach ($selectedFilters as $slug => $values) {
            foreach ((array) $values as $val) {
                if ($slug === 'category') {
                    $activeKeys[] = "filter:category:$val";
                } elseif ($slug === 'vendor') {
                    $activeKeys[] = "filter:vendor:" . md5($val);
                } else {
                    $activeKeys[] = "filter:param:$slug:" . md5($val);
                }
            }
        }

        foreach ($parameters as $slug => $paramId) {
            $possibleValues = DB::table('parameter_values')
                ->where('parameter_id', $paramId)
                ->where('value', '!=', '')
                ->pluck('value')
                ->unique();

            $values = [];

            foreach ($possibleValues as $value) {
                $testKeys = $activeKeys;

                $isActive = isset($selectedFilters[$slug]) &&
                    in_array($value, (array) $selectedFilters[$slug]);

                if (!$isActive) {
                    $testKeys[] = "filter:param:$slug:" . md5($value);
                }

                $count = $this->filterCache->getCountFromKeys($testKeys);

                if ($count > 0 || $isActive) {
                    $values[] = [
                        'value' => $value,
                        'count' => $count,
                        'active' => $isActive,
                    ];
                }
            }

            $result[$slug] = $this->sortFilterItems($values);
        }

        $categories = DB::table('offers')
            ->select('category_id')
            ->whereNotNull('category_id')
            ->where('category_id', '!=', '')
            ->distinct()
            ->pluck('category_id');

        $categoryValues = [];

        foreach ($categories as $value) {
            $testKeys = $activeKeys;

            $isActive = isset($selectedFilters['category']) &&
                in_array($value, (array) $selectedFilters['category']);

            if (!$isActive) {
                $testKeys[] = "filter:category:$value";
            }

            $count = $this->filterCache->getCountFromKeys($testKeys);

            if ($count > 0 || $isActive) {
                $categoryValues[] = [
                    'value' => $value,
                    'count' => $count,
                    'active' => $isActive,
                ];
            }
        }

        $result['category'] = $this->sortFilterItems($categoryValues);

        $vendors = DB::table('offers')
            ->select('vendor')
            ->whereNotNull('vendor')
            ->where('vendor', '!=', '')
            ->distinct()
            ->pluck('vendor');

        $vendorValues = [];

        foreach ($vendors as $value) {
            $testKeys = $activeKeys;

            $isActive = isset($selectedFilters['vendor']) &&
                in_array($value, (array) $selectedFilters['vendor']);

            if (!$isActive) {
                $testKeys[] = "filter:vendor:" . md5($value);
            }

            $count = $this->filterCache->getCountFromKeys($testKeys);

            if ($count > 0 || $isActive) {
                $vendorValues[] = [
                    'value' => $value,
                    'count' => $count,
                    'active' => $isActive,
                ];
            }
        }

        $result['vendor'] = $this->sortFilterItems($vendorValues);

        return $result;
    }
}
