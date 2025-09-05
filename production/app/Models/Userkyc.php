<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Userkyc extends Model
{
    protected $fillable = ['aadharfront', 'aadharback', 'pancard', 'profile', 'user_id'];
}
