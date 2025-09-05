<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Goldloan extends Model
{
    protected $table = "goldloan";
    protected $fillable = ['firstName','middleName','lastName','gender','mobileNumber','goldLoanAmount','pincode','latitude','longitude','email','user_id','created_at','updated_at','disbursementNo','objectUniqeId'];

    public $appends = ['username'];

    public function user(){
        return $this->belongsTo('App\User');
    }

    public function getUpdatedAtAttribute($value)
    {
        return date('d M y - h:i A', strtotime($value));
    }
    public function getCreatedAtAttribute($value)
    {
        return date('d M - H:i', strtotime($value));
    }
    public function getUsernameAttribute()
    {
        $data = '';
        if($this->user_id){
            $user = \App\User::where('id' , $this->user_id)->first(['name', 'id', 'role_id']);
            $data = $user->name." (".$user->id.") (".$user->role->name.")";
        }
        return $data;
    }
}
