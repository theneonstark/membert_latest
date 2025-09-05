<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiCredential extends Model
{
    public $table = "api_credentials";
    protected $fillable = ['api_key', 'aes_key', 'aes_iv', 'user_id'];
    public function getApiKeyAttribute($value)
    {
        return "*******************".substr($value, -6);

    }
}