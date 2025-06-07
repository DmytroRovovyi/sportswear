<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

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
}
