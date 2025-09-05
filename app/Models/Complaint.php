<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    protected $fillable = ['product', 'subject', 'description', 'solution', 'transaction_id', 'status', 'user_id', 'resolve_id','screenshot'];

    public $with = ['complaintsubject'];

    public $appends = ['resolvername', 'username'];

    public function user(){
        return $this->belongsTo('App\Models\User');
    }

    public function resolver(){
        return $this->belongsTo('App\Models\User', 'resolve_id');
    }

    public function getUsernameAttribute()
    {
        $data = '';
        if($this->user_id){
            $user = \App\Models\User::where('id' , $this->user_id)->first(['name', 'id', 'role_id']);
            $data = $user->name." (".$user->id.") (".$user->role->name.")";
        }
        return $data;
    }

    public function getResolvernameAttribute()
    {
        $data = '';
        if($this->resolve_id){
            $user = \App\Models\User::where('id' , $this->resolve_id)->first(['name', 'id', 'role_id']);
            $data = $user->name." (".$user->id.") (".$user->role->name.")";
        }
        return $data;
    }

    public function complaintsubject(){
        return $this->belongsTo('App\Models\Complaintsubject', 'subject');
    }
}
