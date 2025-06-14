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
        $sortBy = $request->input('sort_by', null);
        $allowedFilters = ['brand', 'color', 'appointment', 'gender', 'category_id', 'vendor'];
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

        $placeholders = implode(',', array_fill(0, count($pagedProductIds), '?'));

        switch ($sortBy) {
            case 'price_asc':
                $orderBy = 'ORDER BY products.price ASC';
                break;
            case 'price_desc':
                $orderBy = 'ORDER BY products.price DESC';
                break;
            case 'name_asc':
                $orderBy = 'ORDER BY products.name ASC';
                break;
            case 'name_desc':
                $orderBy = 'ORDER BY products.name DESC';
                break;
            default:
                $orderBy = '';
                break;
        }

        $sql = "
            SELECT
                offers.offer_id,
                offers.category_id,
                offers.vendor,
                products.name,
                products.price,
                products.description
            FROM offers
            JOIN products ON offers.product_id = products.id
            WHERE offers.product_id IN ($placeholders)
            $orderBy
        ";

        $products = DB::select($sql, $pagedProductIds);

        $filtersData = $this->getFiltersData($filters);
        $total = count($productIds);

        return view('index', [
            'products' => $products,
            'filters' => $filtersData,
            'currentPage' => $page,
            'lastPage' => (int) ceil($total / $perPage),
            'total' => $total,
            'sortBy' => $sortBy,
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

        $offerFields = ['category_id', 'vendor'];
        $parameterSlugs = ['brand', 'color', 'gender', 'appointment'];

        $activeKeys = $this->filterCache->getActiveKeysFromSelectedFilters($selectedFilters);

        foreach ($offerFields as $slug) {
            $sql = "
                SELECT DISTINCT `$slug`
                FROM offers
                WHERE `$slug` IS NOT NULL
                  AND `$slug` != ''
                  AND TRIM(`$slug`) != ''
            ";

            $values = DB::select($sql);
            $values = array_map(fn($item) => $item->$slug, $values);

            $filterItems = [];

            foreach ($values as $value) {
                $isActive = isset($selectedFilters[$slug]) && in_array($value, (array) $selectedFilters[$slug]);

                $testKeys = $activeKeys;

                if (!$isActive) {
                    $key = "filter:$slug:" . ($slug === 'vendor' ? md5($value) : $value);
                    $testKeys[] = $key;
                }

                $count = $this->filterCache->getCountFromKeys($testKeys);

                if ($count > 0 || $isActive) {
                    $filterItems[] = [
                        'value' => $value,
                        'count' => $count,
                        'active' => $isActive,
                    ];
                }
            }

            $result[$slug] = $this->sortFilterItems($filterItems);
        }

        foreach ($parameterSlugs as $slug) {
            $paramId = DB::table('parameters')->where('slug', $slug)->value('id');
            if (!$paramId) {
                $result[$slug] = [];
                continue;
            }

            $sql = "
                SELECT DISTINCT value
                FROM parameter_values
                WHERE parameter_id = ?
                  AND value != ''
            ";

            $rows = DB::select($sql, [$paramId]);
            $values = array_map(fn($row) => $row->value, $rows);

            $filterItems = [];

            foreach ($values as $value) {
                $isActive = isset($selectedFilters[$slug]) && in_array($value, (array) $selectedFilters[$slug]);

                $testKeys = $activeKeys;

                if (!$isActive) {
                    $key = "filter:param:$slug:" . md5($value);
                    $testKeys[] = $key;
                }

                $count = $this->filterCache->getCountFromKeys($testKeys);

                if ($count > 0 || $isActive) {
                    $filterItems[] = [
                        'value' => $value,
                        'count' => $count,
                        'active' => $isActive,
                    ];
                }
            }

            $result[$slug] = $this->sortFilterItems($filterItems);
        }

        return $result;
    }
}
