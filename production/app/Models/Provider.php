<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    protected $fillable = ['name', 'recharge1', 'recharge2', 'api_id', 'type', 'status','parameter','maxlength','minlength'];
    public $timestamps = false;

    public function getParameterAttribute($value)
    {
    	return explode(",", $value);
    }

    public function getMaxlengthAttribute($value)
    {
    	return explode(",", $value);
    }

    public function getMinlengthAttribute($value)
    {
    	return explode(",", $value);
    }

    public function api(){
        return $this->belongsTo('App\Models\Api');
    }
}
