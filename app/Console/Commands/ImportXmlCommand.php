<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\FilterCacheService;

class ImportXmlCommand extends Command
{
    protected $signature = 'import:xml {filename=products.xml}';
    protected $description = 'Importing an XML file with products into the database';

    protected $filterCache;

    protected $filterMap = [
        'Англійське найменування' => 'name',
        'Бренд' => 'brand',
        'Колір' => 'color',
        'Призначення' => 'appointment',
        'Розмір постачальника' => 'size',
        'Склад' => 'composition',
        'Стать' => 'gender',
    ];

    public function __construct(FilterCacheService $filterCache)
    {
        parent::__construct();
        $this->filterCache = $filterCache;
    }

    public function handle()
    {
        $filename = $this->argument('filename');
        $path = storage_path("app/import/xml/{$filename}");

        if (!file_exists($path)) {
            $this->error("File not found: {$path}");
            return;
        }

        $xml = simplexml_load_file($path);

        DB::beginTransaction();

        try {
            // Add categories from XML, including parent categories.
            foreach ($xml->shop->categories->category as $category) {
                $id = (string)$category['id'];
                $name = (string)$category;
                $parentId = isset($category['parentId']) ? (string)$category['parentId'] : null;

                DB::update("
                    INSERT INTO categories (id, name, parent_id, created_at, updated_at)
                    VALUES (?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        name = VALUES(name),
                        parent_id = VALUES(parent_id),
                        updated_at = NOW()
                ", [$id, $name, $parentId]);
            }

            // Add product offers with details like price, availability, and vendor.
            foreach ($xml->shop->offers->offer as $offer) {
                $offerId = (string)$offer['id'];
                $available = ((string)$offer['available'] === 'true') ? 1 : 0;
                $productName = (string)$offer->name;
                $price = (float)$offer->price;
                $description = (string)$offer->description;
                $categoryId = (string)$offer->categoryId;
                $currencyId = (string)$offer->currencyId;
                $stockQty = (int)$offer->stock_quantity;
                $vendor = (string)$offer->vendor;
                $vendorCode = (string)$offer->vendorCode;
                $barcode = (string)$offer->barcode;

                DB::update("
                    INSERT INTO products (name, price, description, created_at, updated_at)
                    VALUES (?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        price = VALUES(price),
                        description = VALUES(description),
                        updated_at = NOW()
                ", [$productName, $price, $description]);

                $productId = DB::table('products')->where('name', $productName)->value('id');

                if (empty($productId)) {
                    continue;
                }

                DB::update("
                    INSERT INTO offers (offer_id, product_id, category_id, currency_id, available, stock_quantity, vendor, vendor_code, barcode, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        product_id = VALUES(product_id),
                        category_id = VALUES(category_id),
                        currency_id = VALUES(currency_id),
                        available = VALUES(available),
                        stock_quantity = VALUES(stock_quantity),
                        vendor = VALUES(vendor),
                        vendor_code = VALUES(vendor_code),
                        barcode = VALUES(barcode),
                        updated_at = NOW()
                ", [
                    $offerId, $productId, $categoryId, $currencyId,
                    $available, $stockQty, $vendor, $vendorCode, $barcode
                ]);

                // Add product pictures with their order.
                $position = 0;
                foreach ($offer->picture as $pictureUrl) {
                    $url = (string)$pictureUrl;

                    DB::update("
                        INSERT INTO product_pictures (product_id, picture, position, created_at, updated_at)
                        VALUES (?, ?, ?, NOW(), NOW())
                        ON DUPLICATE KEY UPDATE
                            position = VALUES(position),
                            updated_at = NOW()
                    ", [$productId, $url, $position]);

                    $position++;
                }

                // Add product parameters and link them to products.
                foreach ($offer->param as $param) {
                    $ukrParamName = (string)$param['name'];
                    $paramValue = (string)$param;

                    $paramName = $this->filterMap[$ukrParamName] ?? $ukrParamName;

                    $paramSlug = Str::slug($paramName);

                    DB::update("
                            INSERT INTO parameters (name, slug, created_at, updated_at)
                            VALUES (?, ?, NOW(), NOW())
                            ON DUPLICATE KEY UPDATE updated_at = NOW()
                    ", [$paramName, $paramSlug]);

                    $parameterId = DB::table('parameters')->where('slug', $paramSlug)->value('id');

                    DB::update("
                        INSERT INTO parameter_values (parameter_id, value, created_at, updated_at)
                        VALUES (?, ?, NOW(), NOW())
                        ON DUPLICATE KEY UPDATE updated_at = NOW()
                    ", [$parameterId, $paramValue]);

                    $paramValueId = DB::table('parameter_values')
                        ->where('parameter_id', $parameterId)
                        ->where('value', $paramValue)
                        ->value('id');

                    DB::insert("
                        INSERT IGNORE INTO product_parameters (product_id, parameter_value_id)
                        VALUES (?, ?)
                    ", [$productId, $paramValueId]);
                }
            }

            DB::commit();

            // Clear Redis cache for product filters.
            $this->filterCache->clearAll();

            foreach ($xml->shop->offers->offer as $offer) {
                $productId = DB::table('products')->where('name', (string)$offer->name)->value('id');

                if (!$productId) {
                    continue;
                }

                $this->filterCache->addProductToCategory((string)$offer->categoryId, $productId);
                $this->filterCache->addProductToVendor((string)$offer->vendor, $productId);
                $this->filterCache->addProductToAvailability(((string)$offer['available'] === 'true'), $productId);
                $this->filterCache->addProductToAllProducts($productId);

                foreach ($offer->param as $param) {
                    $this->filterCache->addProductToParam((string)$param['name'], (string)$param, $productId);
                }
            }

            $this->info('Import and Redis cache update completed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('An error occurred: ' . $e->getMessage());
        }
    }
}
