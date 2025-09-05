<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Microatmreport extends Model
{
    protected $fillable = ['number','mobile','provider_id','api_id','amount','charge','profit','gst','tds','apitxnid','txnid','payid','refno','description','remark','option1','option2','option3','option4','option5','option6','option7','option8','status','user_id','credit_by','rtype','via','closing','balance','trans_type','product','create_time'];

    public $appends = ['apicode', 'username', 'apiname'];

    public function user(){
        return $this->belongsTo('App\User');
    }

    public function api(){
        return $this->belongsTo('App\Model\Api');
    }
    
    public function provider(){
        return $this->belongsTo('App\Model\Provider');
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
    
    public function getApinameAttribute()
    {
        $data = Api::where('id' , $this->api_id)->first(['name']);
        if($data){
            return $data->name;
        }else{
            return '';
        }
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
