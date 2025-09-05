<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Circle extends Model
{
    protected $fillable = ['state', 'plan_code'];
    public $timestamps = false;
}
