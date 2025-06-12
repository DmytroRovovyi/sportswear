<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class FilterCacheService
{
    /**
     * Clear all filter keys from Redis.
     */
    public function clearAll()
    {
        $keys = Redis::keys('filter:*');
        if (!empty($keys)) {
            Redis::del($keys);
        }
    }

    /**
     * Add the given product ID to the Redis set of all products.
     *
     * @param int $productId
     * @return void
     */
    public function addProductToAllProducts(int $productId): void
    {
        Redis::sadd('filter:all_products', $productId);
    }

    /**
     * Add a product ID to the Redis set for a specific category.
     *
     * @param string $categoryId
     * @param int $productId
     */
    public function addProductToCategory(string $categoryId, int $productId)
    {
        Redis::sadd("filter:category:$categoryId", $productId);
    }

    protected function getVendorKey(string $vendor): string
    {
        return 'filter:vendor:' . md5($vendor);
    }

    /**
     * Add a product ID to the Redis set for a specific vendor.
     * Uses md5 hash of vendor name as the Redis key to avoid special character issues.
     *
     * @param string $vendor
     * @param int $productId
     */
    public function addProductToVendor(string $vendor, int $productId)
    {
        $key = $this->getVendorKey($vendor);
        Redis::sadd($key, $productId);
    }

    /**
     * Add a product ID to the Redis set based on availability status.
     *
     * @param bool $available
     * @param int $productId
     */
    public function addProductToAvailability(bool $available, int $productId)
    {
        $key = $available ? 'available' : 'not_available';
        Redis::sadd("filter:availability:$key", $productId);
    }

    /**
     * Add a product ID to the Redis set for a specific parameter and its value.
     * Parameter name and value are slugified to create the Redis key.
     *
     * @param int $productId
     * @return void
     */
    public function addProductToParam(int $productId): void
    {
        $parametersRaw = DB::select("
            SELECT id, slug
            FROM parameters
            WHERE slug IN ('brand', 'color', 'appointment', 'gender')
        ");

        if (empty($parametersRaw)) {
            return;
        }

        $parameters = [];
        foreach ($parametersRaw as $param) {
            $parameters[$param->id] = $param->slug;
        }

        $paramValues = DB::select("
            SELECT pv.parameter_id, pv.value
            FROM parameter_values pv
            INNER JOIN product_parameters pp ON pp.parameter_value_id = pv.id
            WHERE pp.product_id = ?
        ", [$productId]);

        foreach ($paramValues as $param) {
            if (!isset($parameters[$param->parameter_id])) {
                continue;
            }

            $slug = $parameters[$param->parameter_id];
            $value = $param->value;
            $hashedValue = md5($value);

            $key = "filter:param:$slug:$hashedValue";
            Redis::sadd($key, $productId);
        }
    }

    /**
     * Rebuild cache redis.
     */
    public function rebuildFromDatabase(): void
    {
        // Clear Redis cache for product filters.
        $this->clearAll();

        // Add products by categories, vendors and availability.
        $offers = DB::select(
            'SELECT product_id, category_id, vendor, available FROM offers'
        );
        foreach ($offers as $offer) {
            $this->addProductToCategory((string)$offer->category_id, $offer->product_id);
            $this->addProductToVendor((string)$offer->vendor, $offer->product_id);
            $this->addProductToAvailability((bool)$offer->available, $offer->product_id);
            $this->addProductToAllProducts($offer->product_id);
            $this->addProductToParam($offer->product_id);
        }
    }

    /**
     * Retrieve filtered product IDs from Redis based on the given filters.
     *
     * @param array $filters
     * @return array
     */
    public function getFilteredProductIds(array $filters): array
    {
        $redisKeysByParam = [];

        foreach ($filters as $key => $values) {
            $values = (array) $values;

            $keysForThisFilter = [];

            foreach ($values as $value) {
                switch ($key) {
                    case 'category':
                        $keysForThisFilter[] = "filter:category:$value";
                        break;

                    case 'vendor':
                        $keysForThisFilter[] = "filter:vendor:" . md5($value);
                        break;

                    case 'availability':
                        $availabilityKey = $value ? 'available' : 'not_available';
                        $keysForThisFilter[] = "filter:availability:$availabilityKey";
                        break;

                    default:
                        $paramSlug = Str::slug($key);
                        $keysForThisFilter[] = "filter:param:$paramSlug:" . md5($value);
                        break;
                }
            }

            if (count($keysForThisFilter) === 1) {
                $redisKeysByParam[] = $keysForThisFilter[0];
            } elseif (count($keysForThisFilter) > 1) {
                $tempKey = 'temp:union:' . md5(implode(',', $keysForThisFilter));
                Redis::del($tempKey);
                Redis::sunionstore($tempKey, ...$keysForThisFilter);
                $redisKeysByParam[] = $tempKey;
            }
        }

        if (empty($redisKeysByParam)) {
            $cachedIds = Redis::smembers('filter:all_products') ?: [];
        } else {
            $cachedIds = Redis::sinter(...$redisKeysByParam);
        }

        foreach ($redisKeysByParam as $key) {
            if (str_starts_with($key, 'temp:union:')) {
                Redis::del($key);
            }
        }

        if (empty($cachedIds)) {
            return [];
        }

        return DB::table('offers')
            ->whereIn('product_id', $cachedIds)
            ->pluck('product_id')
            ->toArray();
    }

    /**
     *
     *
     * @param array $keys
     * @return int
     */
    public function getCountFromKeys(array $keys): int
    {
        if (empty($keys)) {
            return 0;
        }

        $tempKey = 'temp:intersect:' . md5(implode('|', $keys));
        Redis::sinterstore($tempKey, ...$keys);
        $count = Redis::scard($tempKey);
        Redis::del($tempKey);

        return $count;
    }
}
