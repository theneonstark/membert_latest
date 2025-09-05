<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Userbank extends Model
{
    protected $fillable = ['name', 'account', 'ifsc', 'bank', 'user_id', 'type'];
}
