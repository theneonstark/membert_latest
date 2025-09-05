<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bbpsagent extends Model
{
    public $timestamps = false;
    protected $fillable = ['agentid', 'name', 'mobile', 'status', 'alternatecode', 'user_id', 'geocode', 'shopname', 'address', 'city', 'pincode', 'state', 'created_at'];

    public $with = ['user'];
    public $appends = ['username'];

    public function user(){
        return $this->belongsTo('App\User');
    }

    public function getUsernameAttribute()
    {
        $data = '';
        if($this->user_id){
            $user = \App\User::where('id' , $this->user_id)->first(['name', 'id', 'role_id']);
            $data = $user->name." (".$user->id.") <br>(".$user->role->name.")";
        }
        return $data;
    }
}
