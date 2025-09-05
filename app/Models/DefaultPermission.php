<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DefaultPermission extends Model
{
    protected $fillable = ['permission_id', 'role_id'];
    public $timestamps = false;
}
