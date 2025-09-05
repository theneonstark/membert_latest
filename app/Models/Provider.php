<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    protected $fillable = ['name', 'recharge1', 'recharge2', 'recharge3', 'api_id', 'type', 'status', 'paramcount','manditcount','paramname','maxlength','minlength','regex','fieldtype','ismandatory'];

    public $timestamps = false;

    public function getParamnameAttribute($value)
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

    public function getRegexAttribute($value)
    {
    	return explode(",", $value);
    }

    public function getIsmandatoryAttribute($value)
    {
    	return explode(",", $value);
    }

    public function getFieldtypeAttribute($value)
    {
        return explode(",", $value);
    }

    public function api(){
        return $this->belongsTo('App\Models\Api');
    }
}
