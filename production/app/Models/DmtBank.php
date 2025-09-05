<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DmtBank extends Model
{
    protected $fillable = ['bankname', 'bankcode', 'bankid', 'masterifsc'];
    public $timestamps = false;
}
