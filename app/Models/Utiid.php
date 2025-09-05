<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Utiid extends Model
{
    protected $fillable = ['vleid', 'vlepassword', 'name', 'location', 'contact_person', 'pincode', 'state', 'email', 'mobile', 'user_id', 'sender_id', 'status', 'api_id', 'txnid', 'payid'];

    public $appends = ['apicode', 'username'];

    public function user(){
        return $this->belongsTo('App\Models\User');
    }

    public function api(){
        return $this->belongsTo('App\Models\Api');
    }

    public function getUpdatedAtAttribute($value)
    {
        return date('d M y - h:i A', strtotime($value));
    }
    public function getCreatedAtAttribute($value)
    {
        return date('d M - H:i', strtotime($value));
    }

    public function getApicodeAttribute()
    {
        $data = Api::where('id' , $this->api_id)->first(['code']);
        if($data){
            return $data->code;
        }else{
            return '';
        }
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
}
