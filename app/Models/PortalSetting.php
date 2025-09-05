<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalSetting extends Model
{
    protected $fillable = ['name', 'code', 'value', 'company_id'];
    public $timestamps = false;
}
