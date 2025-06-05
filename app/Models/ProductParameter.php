<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductParameter extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'product_id',
        'parameter_value_id',
    ];

    /**
     * Get the product that owns this relation.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the parameter value that owns this relation.
     */
    public function parameterValue()
    {
        return $this->belongsTo(ParameterValue::class);
    }
}
