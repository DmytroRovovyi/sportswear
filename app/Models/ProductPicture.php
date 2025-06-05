<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPicture extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'product_id',
        'picture',
        'position',
    ];

    /**
     * All pictures for one product.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
