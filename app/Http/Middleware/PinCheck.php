<?php

namespace App\Http\Middleware;

use Closure;

class PinCheck
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
        if(
            \Request::is("mobile/fundpay/transaction") ||
            \Request::is("fund/other/transaction")
        ){
            switch ($post->type) {
                case 'getotp':
                case 'addbank':
                case 'bankchange':
                case 'getaccount':
                    return $next($post);
                    break;
            }
        }

        if(
            \Request::is("mobile/service/request/submit") ||
            \Request::is("service/request/submit")
        ){
            switch ($post->type) {
                case 'insuranceclaim':
                    return $next($post);
                    break;
            }
        }

        if(
            \Request::is("mobile/bbps/transaction") ||
            \Request::is("bbps/payment")
        ){
            switch ($post->type) {
                case 'getbilldetails':
                    return $next($post);
                    break;
            }
        }

        if(
            \Request::is("pancard/payment")
        ){
            switch ($post->actiontype) {
                case 'getvleid':
                case 'vleid':
                    return $next($post);
                    break;
            }
        }

        if(
            \Request::is("mobile/dmt/v2/transaction") ||
            \Request::is("mobile/dmt/v3/transaction") ||
            \Request::is("mobile/dmt/v4/transaction") ||
            \Request::is("dmt/v2/transaction") ||
            \Request::is("dmt/v3/transaction") ||
            \Request::is("dmt/v4/transaction")
        ){
            switch ($post->type) {
                case 'getbanks':
                case 'SenderDetails':
                case 'SenderRegister':
                case 'VerifySender':
                case 'ResendSenderOtp':
                case 'AllRecipient':
                case 'GetRecipient':
                case 'RegRecipient':
                case 'DelRecipient':
                case 'BankList':
                case 'VerifyBankAcct':
                case 'GetCCFFee':
                case 'MultiTxnStatus':

                case 'state':
                case 'getbank':
                case 'verification':
                case 'outletotp':
                case 'getbeneficiary':
                case 'outletregister':
                case 'mobilechange':
                case 'mobilechangeverify':
                case 'benedelete':
                case 'benedeletevalidate':
                case 'registration':
                case 'registrationValidate':
                case 'addbeneficiary':
                case 'beneverify':
                case 'accountverification':
                case 'refundotp':
                case 'getrefund':

                case 'getbanks':
                case 'verification':
                case 'otp':
                case 'registration':
                case 'registrationVerification':
                case 'addbeneficiary':
                case 'beneverify':
                case 'accountverification':
                case 'benedelete':
                    return $next($post);
                    break;
            }
        }

        try {
            $pincheck = \DB::table('portal_settings')->where('code', "pincheck")->first();
            if($pincheck && $pincheck->value == "yes"){
                if(!\Myhelper::can('pin_check', $post->user_id)){
                    $code = \DB::table('pindatas')->where('user_id', $post->user_id)->where('pin', \Myhelper::encrypt($post->pin, "antliaFin@@##2025500"))->first();

                    if(!$code){
                        $attempt = \DB::table('pindatas')->where('user_id', $post->user_id)->first();
                        if($attempt && $attempt->status == "block"){
                            return response()->json(['statuscode' => "ERR", "message" => "Transaction Pin is block, reset tpin"]);
                        }

                        if($attempt && $attempt->attempt >2){
                            \DB::table('pindatas')->where('user_id', $post->user_id)->update(["status" => "block"]);
                            return response()->json(['statuscode' => "ERR", "message" => "Transaction Pin is block, reset tpin"]);
                        }

                        \DB::table('pindatas')->where('user_id', $post->user_id)->increment("attempt", 1);
                        return response()->json(['statuscode' => "ERR", "message" => "Transaction Pin is incorrect"]);
                    }else{
                        $attempt = \DB::table('pindatas')->where('user_id', $post->user_id)->first();
                        if($attempt->status == "block"){
                            return response()->json(['statuscode' => "ERR", "message" => "Transaction Pin is block, reset tpin"]);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            return response()->json(['statuscode' => "ERR", "message" => $e->getMessage()]);
        }
        return $next($post);
    }
}
