<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParameterValue extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'parameter_id',
        'value',
    ];

    /**
     * Get the parameter this value belongs to.
     */
    public function parameter()
    {
        return $this->belongsTo(Parameter::class);
    }
}
