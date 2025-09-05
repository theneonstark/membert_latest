<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogSession extends Model
{
    protected $fillable = ['user_id', 'ip_address', 'user_agent','gps_location','ip_location','device_id'];

    public $appends = ['username'];
    
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
