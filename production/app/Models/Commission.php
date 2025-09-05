<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    protected $fillable = ['slab', 'type', 'whitelable', 'md', 'distributor', 'retailer', 'retaillite', 'scheme_id'];

    public $with = ['provider'];

    public function provider(){
        return $this->belongsTo('App\Models\Provider', 'slab');
    }
}
