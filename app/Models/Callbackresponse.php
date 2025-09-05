<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Callbackresponse extends Model
{
    protected $fillable = ['user_id', 'transaction_id', 'product', 'status', 'response', 'url'];
}
