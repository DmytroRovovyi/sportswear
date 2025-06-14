<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\SortsFilterItems;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\FilterCacheService;
use Illuminate\Support\Facades\Log;

class CatalogFilters extends Controller
{
    use SortsFilterItems;
    protected FilterCacheService $filterCache;

    /**
     * Constructor
     */
    public function __construct(FilterCacheService $filterCache)
    {
        $this->filterCache = $filterCache;
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

            $activeKeys = $this->filterCache->getActiveKeysFromSelectedFilters($filters);
            $parameters = DB::select('
                SELECT id, slug, name
                FROM parameters
                WHERE slug IN (?, ?, ?, ?)
            ', ['brand', 'color', 'appointment', 'gender']);

            foreach ($parameters as $parameter) {
                $paramSlug = $parameter->slug;

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

                    // Виключаємо поточний фільтр зі списку активних
                    $keysExcludingCurrent = array_filter($activeKeys, function ($key) use ($paramSlug) {
                        return !str_contains($key, "filter:param:$paramSlug:");
                    });

                    $testKeys = $keysExcludingCurrent;
                    $testKeys[] = "filter:param:$paramSlug:" . md5($value);

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
                $isActive = isset($filters['category_id']) && in_array($value, (array) $filters['category_id']);

                $keysExcludingCurrent = array_filter($activeKeys, function ($key) {
                    return !str_starts_with($key, 'filter:category_id:');
                });

                $testKeys = $keysExcludingCurrent;
                $testKeys[] = "filter:category_id:$value";

                $count = $this->filterCache->getCountFromKeys($testKeys);

                $categoryItems[] = [
                    'value' => $value,
                    'count' => $count,
                    'active' => $isActive,
                ];
            }

            $categoryItems = $this->sortFilterItems($categoryItems);

            $result[] = [
                'slug' => 'category_id',
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

                $keysExcludingCurrent = array_filter($activeKeys, function ($key) {
                    return !str_starts_with($key, 'filter:vendor:');
                });

                $testKeys = $keysExcludingCurrent;
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
