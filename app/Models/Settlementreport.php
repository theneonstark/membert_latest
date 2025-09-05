<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settlementreport extends Model
{
    protected $fillable = ['status', 'user_id', 'amount'];
}
