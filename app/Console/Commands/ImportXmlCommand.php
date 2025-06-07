<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportXmlCommand extends Command
{
    protected $signature = 'import:xml {filename=products.xml}';
    protected $description = 'Importing an XML file with products into the database';

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
            // Categories.
            foreach ($xml->shop->categories->category as $category) {
                $id = (string)$category['id'];
                $name = addslashes((string)$category);

                $parentIdRaw = isset($category['parentId']) ? "'".(string)$category['parentId']."'" : "NULL";

                DB::statement("
                INSERT INTO categories (id, name, parent_id, created_at, updated_at)
                VALUES ('$id', '$name', $parentIdRaw, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    name = '$name',
                    parent_id = $parentIdRaw,
                    updated_at = NOW()
                ");
            }

            // Offers.
            foreach ($xml->shop->offers->offer as $offer) {
                $offerId = (string)$offer['id'];
                $available = ((string)$offer['available'] === 'true') ? 1 : 0;
                $productName = addslashes((string)$offer->name);
                $price = (float)$offer->price;
                $description = addslashes((string)$offer->description);
                $categoryId = (string)$offer->categoryId;
                $currencyId = addslashes((string)$offer->currencyId);
                $stockQty = (int)$offer->stock_quantity;
                $vendor = addslashes((string)$offer->vendor);
                $vendorCode = addslashes((string)$offer->vendorCode);
                $barcode = addslashes((string)$offer->barcode);

                // Products.
                DB::statement("
                    INSERT INTO products (name, price, description, created_at, updated_at)
                    VALUES ('$productName', $price, '$description', NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        price = $price,
                        description = '$description',
                        updated_at = NOW()
                ");

                // Get ID product.
                $productId = DB::table('products')->where('name', $productName)->value('id');

                // Offers.
                if (empty($productId)) {
                    continue;
                }

                DB::statement("
                    INSERT INTO offers (offer_id, product_id, category_id, currency_id, available, stock_quantity, vendor, vendor_code, barcode, created_at, updated_at)
                    VALUES ('$offerId', '$productId', '$categoryId', '$currencyId', $available, $stockQty, '$vendor', '$vendorCode', '$barcode', NOW(), NOW())
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
                ");

                // Pictures.
                $position = 0;
                foreach ($offer->picture as $pictureUrl) {
                    $url = addslashes((string)$pictureUrl);

                    DB::statement("
                        INSERT INTO product_pictures (product_id, picture, position, created_at, updated_at)
                        VALUES ('$productId', '$url', $position, NOW(), NOW())
                        ON DUPLICATE KEY UPDATE
                        position = VALUES(position),
                        updated_at = NOW()
                    ");

                    $position++;
                }

                // Parameters.
                foreach ($offer->param as $param) {
                    $paramName = addslashes((string)$param['name']);
                    $paramSlug = Str::slug($paramName);
                    $paramValue = addslashes((string)$param);

                    DB::statement("
                        INSERT INTO parameters (name, slug, created_at, updated_at)
                        VALUES ('$paramName', '$paramSlug', NOW(), NOW())
                        ON DUPLICATE KEY UPDATE
                            updated_at = NOW()
                    ");

                    $parameterId = DB::table('parameters')->where('slug', $paramSlug)->value('id');

                    // Parameters value.
                    DB::statement("
                        INSERT INTO parameter_values (parameter_id, value, created_at, updated_at)
                        VALUES ('$parameterId', '$paramValue', NOW(), NOW())
                        ON DUPLICATE KEY UPDATE
                            updated_at = NOW()
                    ");

                    $paramValueId = DB::table('parameter_values')
                        ->where('parameter_id', $parameterId)
                        ->where('value', $paramValue)
                        ->value('id');

                    // Linking the product to the parameter value.
                    DB::statement("
                        INSERT IGNORE INTO product_parameters (product_id, parameter_value_id)
                        VALUES ('$productId', '$paramValueId')
                    ");
                }
            }

            DB::commit();
            $this->info('Import completed successfully.');
        } catch (Exception $e) {
            DB::rollBack();
            $this->error('An error occurred: ' . $e->getMessage());
        }
    }
}
