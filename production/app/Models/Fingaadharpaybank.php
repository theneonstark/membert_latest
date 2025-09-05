<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fingaadharpaybank extends Model
{
    protected $fillable = ['bankName','iinoo'];
    public $timestamps = false;
}
