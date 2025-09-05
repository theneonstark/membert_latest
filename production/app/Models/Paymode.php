<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Paymode extends Model
{
    protected $fillable = ['name', 'status'];
    public $timestamps = false;
}
