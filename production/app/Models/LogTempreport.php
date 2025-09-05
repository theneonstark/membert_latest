<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogTempreport extends Model
{
    protected $fillable = ['account', 'user_id', 'create_time'];
}
