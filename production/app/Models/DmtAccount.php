<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DmtAccount extends Model
{
    protected $fillable = ['account', 'ifsc', 'bankname'];
}
