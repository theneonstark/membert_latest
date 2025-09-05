<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fundbank extends Model
{
    protected $fillable = ['name', 'account', 'ifsc', 'branch', 'status', 'user_id'];

    public $appends = ['username'];

    public function user(){
        return $this->belongsTo('App\Models\User')->select();
    }

    public function getUpdatedAtAttribute($value)
    {
        return date('d M y - h:i A', strtotime($value));
    }

    public function getCreatedAtAttribute($value)
    {
        return date('d M y - h:i A', strtotime($value));
    }

    public function getUsernameAttribute()
    {
        $data = '';
        if($this->user_id){
            $user = \App\Models\User::where('id' , $this->user_id)->first(['name', 'id', 'role_id']);
            $data = $user->name." (".$user->id.") <br>(".$user->role->name.")";
        }
        return $data;
    }
}
