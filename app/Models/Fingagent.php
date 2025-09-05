<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fingagent extends Model
{
    protected $fillable = ['merchantLoginId','merchantLoginPin','merchantName','merchantAddress','merchantCityName','merchantState','merchantPhoneNumber','merchantEmail','merchantShopName','userPan','merchantPinCode','merchantAadhar', 'aadharPic','pancardPic','status','via','user_id','remark'];

    public $with = ['user'];

    public function user(){
         return $this->belongsTo('App\Models\User')->select(['id', 'name', 'mobile', 'role_id']);
     }
}
