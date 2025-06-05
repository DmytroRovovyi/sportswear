<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parameter extends Model
{
    /**
     * Allow mass assignment for these fields.
     */
    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * Relationship: A parameter can have many parameter values.
     */
    public function values()
    {
        return $this->hasMany(ParameterValue::class);
    }
}
