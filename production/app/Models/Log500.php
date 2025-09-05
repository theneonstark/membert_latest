<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Log500 extends Model
{
    protected $fillable = ['file','log', 'line'];
}
