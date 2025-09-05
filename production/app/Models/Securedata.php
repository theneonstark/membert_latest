<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Securedata extends Model
{
	protected $fillable = ['apptoken', 'ip','user_id', 'last_activity'];
	public $appends = ['username'];
    
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
