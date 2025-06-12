<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\FilterCacheService;
use Illuminate\Support\Facades\Log;

class CatalogFilters extends Controller
{
    protected FilterCacheService $filterCache;

    public function __construct(FilterCacheService $filterCache)
    {
        $this->filterCache = $filterCache;
    }

    /**
     * Sorts filter items by active status, count, and value name.
     *
     * @param array $items
     * @return array
     */
    private function sortFilterItems(array $items): array
    {
        usort($items, function ($a, $b) {

            // Active sort value for arrays.
            if ($a['active'] !== $b['active']) {
                return $a['active'] ? -1 : 1;
            }

            // Count sort value for arrays.
            if (($a['count'] > 0) !== ($b['count'] > 0)) {
                return $a['count'] > 0 ? -1 : 1;
            }

            return strcmp((string) $a['value'], (string) $b['value']);
        });

        return $items;
    }


    /**
     * Get a list of all available filters with counts depending on current active filters.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function filters(Request $request)
    {
        try {
            $filters = $request->query('filter', []);

            if (!is_array($filters)) {
                return response()->json([
                    'message' => 'Invalid filters format.',
                ], 400);
            }
            $result = [];

            // Load all parameters that should be available as filters.
            $parameters = DB::select('
                SELECT id, slug, name
                FROM parameters
                WHERE slug IN (?, ?, ?, ?)
            ', ['brand', 'color', 'appointment', 'gender']);

            // Generate current active Redis keys for filtering.
            $activeKeys = [];
            foreach ($filters as $slug => $values) {
                foreach ((array) $values as $value) {
                    $activeKeys[] = "filter:param:$slug:" . md5($value);
                }
            }

            foreach ($parameters as $parameter) {
                $paramSlug = $parameter->slug;

                // Get all unique values for this parameter.
                $values = DB::select('
                    SELECT DISTINCT value
                    FROM parameter_values
                    WHERE parameter_id = ? AND value != ""
                    ORDER BY value
                ', [$parameter->id]);

                $filterItems = [];
                $usedValues = [];

                foreach ($values as $valueObj) {
                    $value = $valueObj->value;
                    $normalizedValue = trim(mb_strtolower($value));

                    if ($normalizedValue === '' || in_array($normalizedValue, $usedValues, true)) {
                        continue;
                    }

                    $usedValues[] = $normalizedValue;

                    $isActive = isset($filters[$paramSlug]) && in_array($value, (array) $filters[$paramSlug]);

                    // Generate test keys by merging active keys with current value
                    $testKeys = $activeKeys;
                    $testKeys[] = "filter:param:$paramSlug:" . md5($value);

                    // Get the intersection count from Redis
                    $count = $this->filterCache->getCountFromKeys($testKeys);

                    $filterItems[] = [
                        'value' => $value,
                        'count' => $count,
                        'active' => $isActive,
                    ];
                }

                $filterItems = $this->sortFilterItems($filterItems);

                $result[] = [
                    'name' => $parameter->name,
                    'slug' => $paramSlug,
                    'values' => $filterItems,
                ];
            }

            $categories = DB::select('
                SELECT DISTINCT category_id as id
                FROM offers
                WHERE category_id IS NOT NULL AND category_id != ""
                ORDER BY category_id
            ');

            $categoryItems = [];
            foreach ($categories as $category) {
                $value = $category->id;
                $isActive = isset($filters['category']) && in_array($value, (array) $filters['category']);
                $testKeys = $activeKeys;
                $testKeys[] = "filter:category:$value";

                $count = $this->filterCache->getCountFromKeys($testKeys);

                $categoryItems[] = [
                    'value' => $value,
                    'count' => $count,
                    'active' => $isActive,
                ];
            }

            $categoryItems = $this->sortFilterItems($categoryItems);

            $result[] = [
                'slug' => 'category',
                'values' => $categoryItems,
            ];

            $vendors = DB::select('
                SELECT DISTINCT vendor
                FROM offers
                WHERE vendor IS NOT NULL AND vendor != ""
                ORDER BY vendor
            ');

            $vendorItems = [];
            foreach ($vendors as $vendorObj) {
                $value = $vendorObj->vendor;
                $isActive = isset($filters['vendor']) && in_array($value, (array) $filters['vendor']);
                $testKeys = $activeKeys;
                $testKeys[] = "filter:vendor:" . md5($value);

                $count = $this->filterCache->getCountFromKeys($testKeys);

                $vendorItems[] = [
                    'value' => $value,
                    'count' => $count,
                    'active' => $isActive,
                ];
            }

            $vendorItems = $this->sortFilterItems($vendorItems);

            $result[] = [
                'slug' => 'vendor',
                'values' => $vendorItems,
            ];

            if (empty($result)) {
                return response()->json([
                    'message' => 'No filters available.',
                    'data' => [],
                ]);
            }

            return response()->json($result);
        } catch (\Throwable $e) {

            Log::error('Error in CatalogFilters@filters', ['message' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to load filters.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
