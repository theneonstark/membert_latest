<?php

namespace App\Http\Middleware;

use Closure;

class AndroidCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // if(!isset($_SERVER["HTTP_X_API_KEY"])){
        //     return response()->json(['statuscode'=>'ERR', 'status'=>'ERR', 'message' => "Kindly update app from play store"]);
        // }
        // $key = \DB::table("androidkeys")->where("keydata", $_SERVER["HTTP_X_API_KEY"])->first();
        // if(!$key){
        //     return response()->json(['statuscode'=>'UA', 'status'=>'UA', 'message' => "Unauthorize Access Key"]);
        // }
        
        // if($_SERVER['HTTP_AUTHORIZATION'] != "Basic ".base64_encode($key->username.":".$key->password)){
        //     return response()->json(['statuscode'=>'UA', 'status'=>'UA', 'message' => "Unauthorize Access auth"]);
        // }

        // if (strpos($_SERVER['HTTP_USER_AGENT'], 'Dalvik')) {
        //     return response()->json(['statuscode'=>'UA', 'status'=>'UA', 'message' => "Unauthorize Access" ]);
        // }

        if(\Request::is('mobile/v1/matm/update')
            || \Request::is("mobile/getversioncode")
            || \Request::is("mobile/sendcrash")
            || \Request::is('mobile/auth/v1')
            || \Request::is('mobile/auth/reset/request')
            || \Request::is('mobile/auth/reset')
            || \Request::is('mobile/auth/register')
            || \Request::is('mobile/getpan')
            || \Request::is('mobile/getaadhar')
            || \Request::is('mobile/getaadharotp')
            || \Request::is('mobile/checkaadharotp')
            || \Request::is('mobile/onboard')
            || \Request::is('mobile/completekyc')
            || \Request::is('mobile/changePassword')
        ){
            return $next($request);
        }

        $request['user_id'] = intval($request->user_id);
        $apptoken = \App\Models\Securedata::where('apptoken', $request->apptoken)->where('user_id', $request->user_id)->first();
        if(!$apptoken){
            return response()->json(['statuscode'=>'UA', 'status'=>'UA', 'message' => "Unauthorize Access Ip"]);
        }else{
            \App\Models\Securedata::where('apptoken', $request->apptoken)->where('user_id', $request->user_id)->update(['last_activity' => time()]);
        }

        $user = \App\Models\User::where('id', $apptoken->user_id)->first();

        if($user->status == "blocked"){
            return response()->json(['statuscode'=>'ERR', 'message' => "Account Blocked"]);
        }

        if($user->company->status == "0" && $user->id != "489"){
            return response()->json(['statuscode'=>'ERR', 'message' => "Service Down"]);
        }
        
        if(!in_array($user->kyc, ["verified", "approved"]) && (!\Request::is('mobile/getbalance') && !\Request::is('mobile/changePassword'))){
            return response()->json(['statuscode'=>'ERR', 'message' => "Kyc ".$user->kyc]);
        }
        
        if($user->permission_change == "yes"){
            \Storage::disk('permission')->delete("permissions/permission".$user->id);
            \DB::table("users")->where("id", $user->id)->update(['permission_change' => "no"]);
        }
        
        $request['via'] = "app";
        return $next($request);
    }
}
