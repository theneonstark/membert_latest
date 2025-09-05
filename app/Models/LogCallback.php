<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogCallback extends Model
{
    protected $fillable = ['modal', 'txnid', 'request', 'response'];

    public function setHeaderAttribute($value)
    {
        $this->attributes['header'] = urlencode(json_encode($value));
    }

    public function setRequestAttribute($value)
    {
        $this->attributes['request'] = urlencode(json_encode($value));
    }

    public function setResponseAttribute($value)
    {
        $this->attributes['response'] = urlencode(json_encode($value));
    }

    public function getHeaderAttribute($value)
    {
        return urldecode($value);
    }

    public function getRequestAttribute($value)
    {
        return urldecode($value);
    }

    public function getResponseAttribute($value)
    {
        return urldecode($value);
    }
}
