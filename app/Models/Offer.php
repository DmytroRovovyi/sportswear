<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'offer_id',
        'product_id',
        'category_id',
        'currency_id',
        'available',
        'stock_quantity',
        'vendor',
        'vendor_code',
        'barcode',
    ];

    /**
     * Get the product associated with the offer.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the category associated with the offer.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
