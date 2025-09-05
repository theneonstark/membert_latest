<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DmtRemitter extends Model
{
    protected $fillable = ['firstname', 'lastname', 'pincode', 'mobile', 'bid', 'btotallimit', 'busedlimit', 'pid', 'ptotallimit', 'pusedlimit', 'rid', 'rtotallimit', 'rusedlimit', 'status'];
}
