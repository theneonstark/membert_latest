<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogWrongTpin extends Model
{
    protected $fillable = ['useragent', 'ip', 'ip_location', 'gps_location', 'user_id'];
}
