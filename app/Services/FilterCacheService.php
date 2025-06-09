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

    /**
     * Add a product ID to the Redis set for a specific vendor.
     * Uses md5 hash of vendor name as the Redis key to avoid special character issues.
     *
     * @param string $vendor
     * @param int $productId
     */
    public function addProductToVendor(string $vendor, int $productId)
    {
        $vendorKey = md5($vendor);
        Redis::sadd("filter:vendor:$vendorKey", $productId);
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
     * @param string $paramName
     * @param string $paramValue
     * @param int $productId
     */
    public function addProductToParam(string $paramName, string $paramValue, int $productId)
    {
        $paramSlug = Str::slug($paramName);
        $paramValueSlug = Str::slug($paramValue);
        Redis::sadd("filter:param:$paramSlug:$paramValueSlug", $productId);
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
        }

        // Add product parameters.
        $params = DB::select(
            'SELECT pp.product_id, p.slug as param_name, pv.value as param_value '
            . 'FROM product_parameters pp '
            . 'JOIN parameter_values pv ON pp.parameter_value_id = pv.id '
            . 'JOIN parameters p ON pv.parameter_id = p.id'
        );

        foreach ($params as $row) {
            $this->addProductToParam($row->param_name, $row->param_value, $row->product_id);
        }
    }
}
