<?php

namespace App\Http\Middleware;

use Closure;

class ApiCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($post, Closure $next)
    {
        if(!\Request::is('api/getip') && !\Request::is('api/getbal/*') && !\Request::is('api/callback/*') && !\Request::is('api/android/*')){
            if(!$post->has('token')){
                return response()->json(['statuscode'=>'ERR','status'=>'ERR','message'=> 'Invalid api token']);
            }
            
            $user = \App\Models\Apitoken::where('ip', $post->ip())->where('token', $post->token)->first();
            if(!$user){
                return response()->json(['statuscode'=>'ERR','status'=>'ERR','message'=> 'Invalid Domain or Ip Address or Api Token']);
            }
        }

        if(\Request::is('api/android/*')){
            if( \Request::is('api/android/auth')
                || \Request::is('api/android/auth/reset/request')
                || \Request::is('api/android/auth/reset')
                || \Request::is('api/android/secure/microatm/update')
                || \Request::is('api/android/recharge/providers')
            ){
                return $next($post);
            }

            if (strpos($_SERVER['HTTP_USER_AGENT'], 'Dalvik')) {
                //return response()->json(['statuscode'=>'ERR', 'message' => "Unauthorize Access" ]);
            }

            $apptoken = \App\Models\Securedata::where('apptoken', $post->apptoken)->where('user_id', $post->user_id)->first();
            if(!$apptoken){
                return response()->json(['statuscode'=>'ERR', 'status'=>'UA', 'message' => "Unauthorize Access Iph"]);
            }else{
                \App\Models\Securedata::where('apptoken', $post->apptoken)->update(['last_activity' => time()]);
            }

            $user = \App\Models\User::where('id', $apptoken->user_id)->first();

            if($user->status == "blocked"){
                return response()->json(['statuscode'=>'ERR', 'status'=>'ERR', 'message' => "Account Blocked"]);
            }

            if($user->kyc == "rejected"){
                return response()->json(['statuscode'=>'ERR', 'status'=>'ERR', 'message' => "Kyc Rejected"]);
            }

            if($user->company->status == "0" && $user->id != "489"){
                return response()->json(['statuscode'=>'ERR', 'status'=>'ERR', 'message' => "Service Down"]);
            }
            
            $post['via'] = "oldeapp";
        }
        
        return $next($post);
    }
}
