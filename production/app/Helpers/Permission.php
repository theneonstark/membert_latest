<?php
namespace App\Helpers;
 
use Illuminate\Http\Request;
use App\Models\UserPermission;
use App\Models\User;
use App\Models\Commission;
use App\Models\Scheme;
use App\Models\Collectionreport;
use App\Models\Report;
use Illuminate\Support\Facades\Mail;

class Permission {
    public static function getAccBalance($id, $wallet)
    {
        $mywallet = \DB::table('users')->where('id', $id)->first([$wallet]);
        $mywallet = (array) $mywallet;
        return $mywallet[$wallet];
    }
    
    public static function FormValidator($rules, $post)
    {
        $validator = \Validator::make($post, array_reverse($rules));
        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $key => $value) {
                $error = $value[0];
            }
            return response()->json(array(
                'statuscode'  => 'ERR',
                'message' => $error
            ));
        }else{
            return "no";
        }
    }

    public static function can($permission , $id="none") {
        if(is_array($permission)){
            $mypermissions = \DB::table('permissions')->whereIn('slug' ,$permission)->get(['id'])->toArray();
            if($mypermissions){
                foreach ($mypermissions as $value) {
                    $mypermissionss[] = $value->id;
                }
            }else{
                $mypermissionss = [];
            }
            $output = UserPermission::where('user_id', $id)->whereIn('permission_id', $mypermissionss)->count();
        }else{
            $mypermission = \DB::table('permissions')->where('slug' ,$permission)->first(['id']);
            if($mypermission){
                $output = UserPermission::where('user_id', $id)->where('permission_id', $mypermission->id)->count();
            }else{
                $output = 0;
            }
        }
        
        if($output > 0){
            return true;
        }
        
        return false;
    }

    public static function mail($view, $data, $mailto, $name, $mailvia, $namevia, $subject)
    {
        Mail::to($mailto)->send(new NotificationMail($view, $data));
        if (\Mail::failures()) {
            return "fail";
        }
        return "success";
    }

    public static function notification($product, $mobile, $name, $email, $resend="no")
    {   
        $otpSend = \DB::table('password_resets')->where("mobile", $mobile)->where("activity", $product)->first();

        if(!$otpSend || $otpSend->resend < 3){
            if($otpSend && $otpSend->last_activity > time()- 120){
                return "Otp can be resend after 2 minutes";
            }

            $otp = rand(111111, 999999);
            $otpmailid   = \App\Models\PortalSetting::where('code', 'otpsendmailid')->first();
            $otpmailname = \App\Models\PortalSetting::where('code', 'otpsendmailname')->first();

            switch ($product) {
                case 'login':
                    $msg = "Dear partner, your login OTP for antila fintech is ".$otp.", please do not share otp with anyone.";
                    $send = true;//\App\Helpers\Permission::otp($mobile, $msg, "1207161726561634222");

                    try {
                        \App\Helpers\Permission::mail('mail.otp', ["name" => $name, "otp" => $otp, "type" => "Login"], $email, $name, $otpmailid->value, $otpmailname->value, "Otp Login");
                    } catch (\Exception $e) {}
                    break;

                case 'device':
                    $msg = "Dear partner, your login OTP for antila fintech is ".$otp.", please do not share otp with anyone.";
                    $send = \App\Helpers\Permission::otp($mobile, $msg, "1207161726561634222");

                    try {
                        \App\Helpers\Permission::mail('mail.otp', ["name" => $name, "otp" => $otp, "type" => "Device Change"], $email, $name, $otpmailid->value, $otpmailname->value, "Otp Login");
                    } catch (\Exception $e) {}
                    break;

                case 'addbank':
                    $msg = "Dear Customer, ".$otp." is the otp to add bank accoount for settlement. Do not disclose it to anyone. Antlia Fintech";
                    $send = \App\Helpers\Permission::otp($mobile, $msg, "1207165519380036667");

                    try {
                        \App\Helpers\Permission::mail('mail.otp', ["name" => $name, "otp" => $otp, "type" => "Add Settlement Bank"], $email, $name, $otpmailid->value, $otpmailname->value, "Otp Login");
                    } catch (\Exception $e) {}
                    break;

                case 'tpin':
                    $msg = "Dear partner, otp for transaction pin generate/reset is ".$otp.". Please do not share otp with anyone. Regards team Antlia Fintech";
                    $send = \App\Helpers\Permission::otp($mobile, $msg, "1207163905310613265");

                    try {
                        \App\Helpers\Permission::mail('mail.otp', ["name" => $name, "otp" => $otp, "type" => "T-Pin"], $email, $name, $otpmailid->value, $otpmailname->value, "T-Pin Reset");
                    } catch (\Exception $e) {}
                    break;
                
                case 'password':
                    $content = "Dear partner, your password reset token for antlia is ".$otp;
                    $send     = \App\Helpers\Permission::otp($mobile, $content, "1207161823102572004");
                    try {
                        \App\Helpers\Permission::mail('mail.otp', ["name" => $name, "otp" => $otp, "type" => "Password Reset"], $email, $name, $otpmailid->value, $otpmailname->value, "Password Reset");
                    } catch (\Exception $e) {}
                    break;
                
                case 'welcome':
                    $content = "Dear Partner, you have been successfully registered, your username is ".$mobile." & password is ".$mobile." Regards Antlia Fintech.";
                    $send = \App\Helpers\Permission::otp($mobile, $content, "1207163905323352288");
                    try {
                        \App\Helpers\Permission::mail('mail.member', ["username" => $mobile, "password" => $mobile, "name" => $name], $email, $name, $otpmailid->value, $otpmailname->value, "Member Registration");
                    } catch (\Exception $e) {}
                    break;
            }

            if($send == "success" && $product != "welcome"){
                if(!$otpSend){
                    \DB::table('password_resets')->insert([
                        'mobile'   => $mobile, 
                        'token'    => \App\Helpers\Permission::encrypt($otp, "antliaFin@@##2025500"),
                        "last_activity" => time(),
                        "activity" => $product
                    ]);
                }else{
                    \DB::table('password_resets')->where("mobile", $mobile)->where("activity", $product)->update([
                        'mobile'   => $mobile, 
                        'token'    => \App\Helpers\Permission::encrypt($otp, "antliaFin@@##2025500"),
                        "last_activity" => time(),
                        "resend"   => $otpSend->resend + 1
                    ]);
                }
            }
            return $send;
        }else{
            return "Otp limit exceed, please contact your service provider";
        }
    }
    
    public static function otp($mobile, $content, $tempid="")
    {
        if($tempid != ""){
            $smsapi = \App\Models\Api::where('code', "r2winsms")->first();
            $url = "https://smsapi.r2win.com/api/push?accesskey=".$smsapi->username."&to=".$mobile."&text=".urlencode($content)."&from=".$smsapi->password."&tid=".$tempid;

            $result = \App\Helpers\Permission::curl($url, "GET", "", [], "report", "otp", $mobile);
            if (isset($result['response']) && $result['response'] != "" && strpos($result['response'], $mobile) !== false) {
                return "success";
            }else{
                $smsdata = \App\Models\Company::where('website', $_SERVER['HTTP_HOST'])->first();
                if(isset($smsdata->senderid)){
                    $url = "http://164.52.195.161/API/SendMsg.aspx?uname=20210572&pass=9Xx9Psk0&send=AREXPM&dest=".$mobile."&msg=".urlencode($content)."&priority=1";
                    $result = \App\Helpers\Permission::curl($url, "GET", "", [], "report", "otp", $mobile);
                    if($result['response'] != ''){
                        if (strpos($result['response'], $mobile) !== false) {
                            return "success";
                        }
                    }
                }
                return "fail";
            }
        }else{
            $smsdata = \App\Models\Company::where('website', $_SERVER['HTTP_HOST'])->first();
            if(isset($smsdata->senderid)){
                $url = "http://164.52.195.161/API/SendMsg.aspx?uname=20210572&pass=9Xx9Psk0&send=AREXPM&dest=".$mobile."&msg=".urlencode($content)."&priority=1";
                $result = \App\Helpers\Permission::curl($url, "GET", "", [], "report", "otp", $mobile);
                if($result['response'] != ''){
                    if (strpos($result['response'], $mobile) !== false) {
                        return "success";
                    }
                }
            }
            return "fail";
        }
    }

    public static function otpValidate($activity, $mobile, $otp)
    {
        $otpSend = \DB::table('password_resets')->where("mobile", $mobile)->where("activity", $activity)->where("token", \App\Helpers\Permission::encrypt($otp, "antliaFin@@##2025500"))->first();

        if($otpSend){
            if($activity != "login"){
                \DB::table('password_resets')->where("id", $otpSend->id)->delete();
            }
            return "success";
        }

        return "failed";
    }

    public static function curl($url , $method, $parameters, $header, $log, $modal, $txnid)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
        curl_setopt($curl, CURLOPT_ENCODING, "");
        curl_setopt($curl, CURLOPT_TIMEOUT, 240);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        if($parameters != ""){
            curl_setopt($curl, CURLOPT_POSTFIELDS, $parameters);
        }

        if(sizeof($header) > 0){
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        }
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        try {
            if($log != "no"){
                switch ($modal) {
                    case 'report':
                        $table = \App\Models\LogReport::query();
                        break;

                    case 'aepsreport':
                        $table = \App\Models\LogAepsreport::query();
                        
                        break;

                    case 'matmreport':
                        $table = \App\Models\LogMicroatmreport::query();
                        break;

                    default :
                        $table = \App\Models\Apilog::query();
                        break;
                }

                if(isset($table)){
                    $table->create([
                        "url" => $url,
                        "modal" => $modal,
                        "txnid" => $txnid,
                        "header" => $header,
                        "request" => $parameters,
                        "response" => $code."/".$err."/".$response
                    ]);
                }
            }
        } catch (\Exception $e) {}
        return ["response" => $response, "error" => $err, 'code' => $code];
    }
    
    public static  function encrypt($plainText, $key)
    {
        $secretKey  = \App\Helpers\Permission::hextobin(md5($key));
        $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
        $openMode   = openssl_encrypt($plainText, 'AES-128-CBC', $secretKey, OPENSSL_RAW_DATA, $initVector);
        $encryptedText = bin2hex($openMode);
        return $encryptedText;
    }
    
    public static function decrypt($encryptedText, $key) {
        $key = \App\Helpers\Permission::hextobin(md5($key));
        $initVector    = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
        $encryptedText = \App\Helpers\Permission::hextobin($encryptedText);
        $decryptedText = openssl_decrypt($encryptedText, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $initVector);
        return $decryptedText;
    }

    public static  function hextobin($hexString) {
        $length = strlen($hexString);
        $binString = "";
        $count = 0;
        while ($count < $length) {
            $subString = substr($hexString, $count, 2);
            $packedString = pack("H*", $subString);
            if ($count == 0) {
                $binString = $packedString;
            } else {
                $binString .= $packedString;
            }
    
            $count += 2;
        }
        return $binString;
    }

    public static function getCommission($amount, $scheme, $slab, $role)
    {       
        $myscheme = Scheme::where('id', $scheme)->first(['status']);
        if($myscheme && $myscheme->status == "1"){
            $comdata = Commission::where('scheme_id', $scheme)->where('slab', $slab)->first();
            if ($comdata) {
                if ($comdata->type == "percent") {
                    $commission = $amount * $comdata[$role] / 100;
                }else{
                    $commission = $comdata[$role];
                }
                if($commission == null){
                    $commission = 0;
                }
            }else{
                $commission = 0;
            }
        }else{
            $commission = 0;
        }
        return $commission;
    }

    public static function commission($report)
    {
        $insert = [
            'number' => $report->number,
            'mobile' => $report->mobile,
            'provider_id' => $report->provider_id,
            'api_id' => $report->api_id,
            'txnid'  => $report->id,
            'payid'  => $report->payid,
            'refno'  => $report->refno,
            'status' => 'success',
            'rtype'  => 'commission',
            'via'    => $report->via,
            'trans_type' => "credit",
            'product'=> $report->product
        ];
        
        if($report->product == "qrcode"){
            $precommission = $report->amount;
        }else{
            $precommission = $report->charge;
        }
        
        $provider      = $report->provider_id;

        $api    = \App\Models\Api::where('id',$report->api_id)->first();
        $parent = User::where('id', $report->user->parent_id)->first(['id', 'mainwallet', 'scheme_id', 'role_id']);

        if($parent->role->slug == "whitelable"){
            $insert['user_id']   = $parent->id;
            $insert['credit_by'] = $report->user_id;

            $parentcommission = \App\Helpers\Permission::getCommission($report->amount, $parent->scheme_id, $provider, 'apiuser');
            $insert['amount'] = $precommission - $parentcommission;
            $insert['tds']    = round(($insert['amount'] * $api->tds)/100, 2);
            
            if($insert['amount'] != 0){
                $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet");
                User::where('id', $insert['user_id'])->increment("mainwallet", $insert["amount"]);
                $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet");
                Report::create($insert);
            }
            
            if($report->product == "payout"){
                Report::where('id', $report->id)->update(['wid' => $parent->id, "wprofit" => $insert['amount']]);
            }else{
                Collectionreport::where('id', $report->id)->update(['wid' => $parent->id, "wprofit" => $insert['amount']]);
            }
        }
    }

    public static function transactionRefund($id, $table, $wallet)
    {
        $report = \DB::table($table)->where('id', $id)->first();
        $count  = \DB::table($table)->where('user_id', $report->user_id)->where('status', 'refunded')->where('txnid', $report->txnid)->count();
        
        if($count == 0){
            $insert = [
                'number'   => $report->number,
                'mobile'   => $report->mobile,
                'provider_id' => $report->provider_id,
                'api_id'   => $report->api_id,
                'apitxnid' => $report->apitxnid,
                'txnid'    => $report->txnid,
                'payid'    => $report->payid,
                'refno'    => "Refund Against ".$report->id,
                'description' => "Transaction Reversed, amount refunded",
                'remark'  => $report->remark,
                'option1' => $report->option1,
                'option2' => $report->option2,
                'option3' => $report->option3,
                'option4' => $report->option4,
                'option5' => $report->option5,
                'option6' => $report->option6,
                'option7' => $report->option7,
                'option8' => $report->option8,
                'status'  => 'refunded',
                'rtype'   => $report->rtype,
                'via'     => $report->via,
                'trans_type' => ($report->trans_type == "credit") ? "debit" : "credit",
                'product' => $report->product,
                'amount'  => $report->amount,
                'profit'  => $report->profit,
                'charge'  => $report->charge,
                'gst'     => $report->gst,
                'tds'     => $report->tds,
                'balance' => \App\Helpers\Permission::getAccBalance($report->user_id, $wallet),
                'user_id' => $report->user_id,
                'credit_by'  => $report->credit_by,
                'created_at' => date("Y-m-d H:i:s"),
                'create_time' => "REFUND-".$report->txnid
            ];

            try {
                $report = \DB::transaction(function () use($table, $report, $wallet, $insert) {
                    $debit = $report->balance - $report->closing;

                    if($debit < 0){
                        $debit = -1 * $debit;
                    }

                    if($report->trans_type == "debit"){
                        User::where('id', $report->user_id)->increment($wallet, $debit);
                    }else{
                        User::where('id', $report->user_id)->decrement($wallet, $debit);
                    }

                    $insert["closing"] = \App\Helpers\Permission::getAccBalance($report->user_id, $wallet);
                    \DB::table($table)->insert($insert);
                });
            } catch (\Exception $e) {
                \DB::table('log_500')->insert([
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                    'log'  => $e->getMessage(),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }

    public static function unpeencrypt($data, $key, $iv)
    {
        $data = json_encode($data, JSON_UNESCAPED_SLASHES);
        $ciphertext_raw = openssl_encrypt($data, "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);
        return bin2hex($ciphertext_raw);
    }
}