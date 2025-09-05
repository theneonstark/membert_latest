<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collectionreport extends Model
{
    protected $fillable = ['number','mobile','provider_id','api_id','amount','bankcharge','charge','profit','gst','tds','apitxnid','txnid','payid','refno','description','remark','option1','option2','option3','option4','option5','option6','option7','option8','status','user_id','credit_by','rtype','via','balance','closing','trans_type','product','create_time', 'transfer_mode'];

    public $appends = ["fundbank", 'apicode', 'apiname', 'username', 'sendername', 'providername'];

    public function user(){
        return $this->belongsTo('App\Models\User');
    }

    public function sender(){
        return $this->belongsTo('App\Models\User', 'credit_by');
    }

    public function api(){
        return $this->belongsTo('App\Models\Api');
    }

    public function provider(){
        return $this->belongsTo('App\Models\Provider');
    }

    public function getUpdatedAtAttribute($value)
    {
        return date('d M y - h:i A', strtotime($value));
    }
    public function getCreatedAtAttribute($value)
    {
        return date('d M - H:i', strtotime($value));
    }

    public function getAmountAttribute($value)
    {
        return round($value, 2);
    }

    public function getFundbankAttribute($value)
    {
        $data = '';
        if($this->product == "fund request"){
            $data = Fundbank::where('id', $this->option1)->first();
        }
        return $data;
    }

    public function getApicodeAttribute()
    {
        $data = Api::where('id' , $this->api_id)->first(['code']);
        return $data->code;
    }

    public function getApinameAttribute()
    {
        $data = Api::where('id' , $this->api_id)->first(['name']);
        return $data->name;
    }

    public function getProvidernameAttribute()
    {
        $data = '';
        if($this->provider_id){
            $provider = Provider::where('id' , $this->provider_id)->first(['name']);
            if($provider){
                $data = $provider->name;
            }else{
                $data = "Not Found";
            }
        }
        return $data;
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

    public function getSendernameAttribute()
    {
        $data = '';
        if($this->credit_by){
            $user = \App\Models\User::where('id' , $this->credit_by)->first(['name', 'id', 'role_id']);
            $data = $user->name." (".$user->id.")<br>(".$user->role->name.")";
        }
        return $data;
    }
    
    public function getRefnoAttribute($value)
    {
        return str_replace('"', "", str_replace(",","",$value));
    }
    
    public function getRemarkAttribute($value)
    {
        return str_replace('"',"",$value);
    }
}
