<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fingagent extends Model
{
    protected $fillable = ['merchantLoginId','merchantLoginPin','merchantName','merchantAddress','merchantCityName','merchantState','merchantPhoneNumber','merchantEmail','merchantShopName','userPan','merchantPinCode','merchantAadhar', 'aadharPic','pancardPic','status','via','user_id','remark'];
}
