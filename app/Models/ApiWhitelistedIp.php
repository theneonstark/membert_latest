<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiWhitelistedIp extends Model
{
    public $table       = "api_whitelisted_ips";
    protected $fillable = ['ip', 'user_id'];
}