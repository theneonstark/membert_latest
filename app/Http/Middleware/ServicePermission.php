<?php

namespace App\Http\Middleware;

use Closure;

class ServicePermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($post, Closure $next, $service)
    {
        $user  = \DB::table("users")->where("id", $post->user_id)->first(["company_id"]);
        switch ($service) {
            case 'aepssettlement':
                if($post->type == "wallet" &&  !\Myhelper::can('aeps_fund_request', $post->user_id)){
                    return response()->json(['status' => "ERR" ,'statuscode' => "ERR" , "message" => "Permission not allowed"]);
                }elseif($post->type == "matmwallet" &&  !\Myhelper::can('matm_fund_request', $post->user_id)){
                    return response()->json(['status' => "ERR" ,'statuscode' => "ERR" , "message" => "Permission not allowed"]);
                }
                break;

            case 'aeps':
                if (!\Myhelper::companycan('ifaeps_service', $user->company_id)) {
                    return response()->json(['status' => "ERR" ,'statuscode' => "ERR", "message" => "Company Permission Not Allowed"]);
                }

                if (!\Myhelper::can('ifaeps_service', $post->user_id)) {
                    return response()->json(['status' => "ERR" ,'statuscode' => "ERR", "message" => "Permission Not Allowed"]);
                }
                break;

            case 'billpay':
                if (!\Myhelper::companycan('billpayment_service', $user->company_id)) {
                    return response()->json(['status' => "ERR" ,'statuscode' => "ERR", "message" => "Permission Not Allowed"]);
                }

                if (!\Myhelper::can('billpayment_service', $post->user_id)) {
                    return response()->json(['status' => "ERR" ,'statuscode' => "ERR", "message" => "Permission Not Allowed"]);
                }
                break;

            case 'dmt':
                if (!\Myhelper::companycan(['dmt1_service', 'dmt2_service', 'dmt3_service'], $user->company_id)) {
                    return response()->json(['status' => "ERR" ,'statuscode' => "ERR", "message" => "Permission Not Allowed"]);
                }

                if (!\Myhelper::can(['dmt1_service', 'dmt2_service', 'dmt3_service'], $post->user_id)) {
                    return response()->json(['status' => "ERR" ,'statuscode' => "ERR", "message" => "Permission Not Allowed"]);
                }
                break;

            case 'matm':
                if (!\Myhelper::companycan('matm_service', $user->company_id)) {
                    return response()->json(['status' => "ERR" ,'statuscode' => "ERR", "message" => "Permission Not Allowed"]);
                }

                if (!\Myhelper::can('matm_service', $post->user_id)) {
                    return response()->json(['status' => "ERR" ,'statuscode' => "ERR", "message" => "Permission Not Allowed"]);
                }
                break;

            case 'recharge':
                if (!\Myhelper::companycan('recharge_service', $user->company_id)) {
                    return response()->json(['status' => "ERR" ,'statuscode' => "ERR", "message" => "Permission Not Allowed"]);
                }

                if (!\Myhelper::can('recharge_service', $post->user_id)) {
                    return response()->json(['status' => "ERR" ,'statuscode' => "ERR", "message" => "Permission Not Allowed"]);
                }
                break;

            case 'utipancard':
                if (!\Myhelper::companycan('utipancard_service', $user->company_id)) {
                    return response()->json(['status' => "ERR" ,'statuscode' => "ERR", "message" => "Permission Not Allowed"]);
                }

                if (!\Myhelper::can('utipancard_service', $post->user_id)) {
                    return response()->json(['status' => "ERR" ,'statuscode' => "ERR", "message" => "Permission Not Allowed"]);
                }
                break;
        }
        return $next($post);
    }
}
