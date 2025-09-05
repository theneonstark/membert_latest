<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fingaepsbank extends Model
{
    protected $fillable = ['activeFlag', 'bankName', 'details', 'remarks'];
    public $timestamps = false;
}
