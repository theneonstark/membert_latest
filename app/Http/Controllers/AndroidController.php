<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class AndroidController extends Controller
{
    public function getversioncode()
    {
        $code = \DB::table('portal_settings')->where('code', 'app_version')->first(['value']);
        $code = explode(",", $code->value);
        return response()->json(["code" => $code[0], "type" => isset($code[1])?$code[1] : "soft"]);
    }
    
    public function cmsdata(Request $post)
    {
        $userCheck = \DB::table("api_credentials")->where("api_key", $post->apiKey)->where("user_id", $post->partnerId)->first();
        if(!$userCheck){
            return response()->json(['statuscode'=>'ERR', 'message' => "Unauthorize Api Key or Partner Id"]);
        }

        $api   = \DB::table("apis")->where("code", "fing_cms")->first();
        $agent = \DB::table("fingagents")->where('user_id', $userCheck->user_id)->where('merchantLoginId', $post->merchantId)->first();

        if($agent){
            $data['statuscode'] = "TXN";
            $data['superMerchantId'] = $api->username;
            $data['secretekey'] = $api->password;
            $data['merchantLoginId'] = $agent->merchantLoginId;
            $data['merchantPhoneNumber'] = $agent->merchantPhoneNumber;
            $data['merchantName'] = $agent->merchantName;

            return response()->json($data);
        }else{
            return response()->json(['statuscode' => "ERR", "message" => "Agent Not Found"]);
        }
    }
}
