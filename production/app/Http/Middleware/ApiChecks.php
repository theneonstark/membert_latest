<?php

namespace App\Http\Middleware;
use Illuminate\Http\Request;
use Closure;

class ApiChecks
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next) {
        if(
            $request->is("api/getip") ||
            $request->is("api/webhook/*")
        ){
            return $next($request);
        }

        if(!isset($_SERVER['HTTP_API_KEY'])){
            return response()->json(['statuscode'=>'ERR', 'message' => "Api Key Is Missing"]);
        }

        $userCheck = \DB::table("api_credentials")->where("api_key", $_SERVER["HTTP_API_KEY"])->first();
        if(!$userCheck){
            return response()->json(['statuscode'=>'ERR', 'message' => "Unauthorize Api Key or Auth Id"]);
        }

        if($request->partner_id != $userCheck->user_id){
            return response()->json(['statuscode'=>'ERR', 'message' => "Invalid Partner Id"]);
        }

        $whitelistIp = \DB::table("api_whitelisted_ips")->where("user_id", $userCheck->user_id)->where("ip", $request->ip())->first();
        if(!$whitelistIp){
            return response()->json(['statuscode'=>'ERR', 'message' => "Request From Unauthorized Ip : ".$request->ip()]);
        }
        
        $request["user_id"]= $userCheck->user_id;
        $request["via"]    = "api";

        $user = \DB::table("users")->where("id", $request->user_id)->first(['status', 'kyc', 'id', 'company_id']);
        if($user->status == "blocked"){
            return response()->json(['statuscode'=>'ERR', 'message' => "Account Blocked"]);
        }

        $company = \DB::table("companies")->where("id", $user->company_id)->first(['status']);
        if(!$company->status){
            return response()->json(['statuscode'=>'ERR', 'message' => "Service Under Maintenance"]);
        }

        if(!in_array($user->kyc, ["verified"])){
            return response()->json(['statuscode'=>'ERR', 'message' => "Kyc ".$user->kyc]);
        }
        
        return $next($request);
    }
}
